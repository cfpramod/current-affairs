<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Ai\BatchBriefingAgent;
use App\Data\BatchBriefingData;
use App\Models\BatchBriefing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * A plain-language briefing for one stored batch assessment. The LLM only
 * words the facts; it never decides. Assessments are immutable, so an LLM
 * briefing is generated once and reused. Without an API key, or if the
 * provider fails, local Codex is tried before the evidence template.
 */
final class BriefBatch
{
    private const string TIMEZONE = 'Europe/Zurich';

    public function __construct(private readonly BriefBatchWithCodex $codex) {}

    /**
     * @param  array<string, mixed>  $assessment
     */
    public function handle(array $assessment): BatchBriefingData
    {
        $stored = BatchBriefing::query()->where('assessment_id', $assessment['assessment_id'])->first();

        if ($stored !== null) {
            return new BatchBriefingData($stored->headline, $stored->summary, $stored->qa_note, $stored->source, $stored->model);
        }

        $facts = $this->facts($assessment);

        if (config('briefing.enabled')) {
            $briefing = $this->generate($assessment, $facts);

            if ($briefing instanceof BatchBriefingData) {
                return $briefing;
            }
        }

        $output = $this->codex->handle($facts);

        if ($output !== null) {
            return $this->store($assessment, $output, 'codex', (string) (config('briefing.codex.model') ?: 'Codex CLI'));
        }

        return $this->template($facts);
    }

    /**
     * @param  array<string, mixed>  $assessment
     * @param  array<string, mixed>  $facts
     */
    private function generate(array $assessment, array $facts): ?BatchBriefingData
    {
        $model = (string) config('briefing.model');

        try {
            $response = (new BatchBriefingAgent)->prompt(
                'Brief the operator on this batch assessment: '.json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                provider: (string) config('briefing.provider'),
                model: $model,
                timeout: (int) config('briefing.timeout'),
            );

            if (! $response instanceof StructuredAgentResponse) {
                return null;
            }

            $output = $response->toArray();
            $headline = is_string($output['headline'] ?? null) ? mb_trim($output['headline']) : '';
            $summary = is_string($output['summary'] ?? null) ? mb_trim($output['summary']) : '';
            $qaNote = is_string($output['qa_note'] ?? null) && mb_trim($output['qa_note']) !== '' ? mb_trim($output['qa_note']) : null;

            if ($headline === '' || $summary === '') {
                return null;
            }

            return $this->store($assessment, ['headline' => $headline, 'summary' => $summary, 'qa_note' => $qaNote], 'llm', $model);
        } catch (Throwable $throwable) {
            Log::warning('API batch briefing unavailable; trying local Codex.', [
                'assessment_id' => $assessment['assessment_id'],
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $assessment
     * @param  array{headline: string, summary: string, qa_note: ?string}  $output
     */
    private function store(array $assessment, array $output, string $source, string $model): BatchBriefingData
    {
        $stored = BatchBriefing::query()->firstOrCreate(['assessment_id' => $assessment['assessment_id']], [
            'batch_id' => $assessment['batch_id'],
            'source' => $source,
            'model' => $model,
            'headline' => mb_substr(mb_trim($output['headline']), 0, 255),
            'summary' => mb_trim($output['summary']),
            'qa_note' => filled($output['qa_note']) ? mb_trim($output['qa_note']) : null,
        ]);

        return new BatchBriefingData($stored->headline, $stored->summary, $stored->qa_note, $stored->source, $stored->model);
    }

    /**
     * The facts the briefing may use, with times in Basel local time.
     *
     * @param  array<string, mixed>  $assessment
     * @return array<string, mixed>
     */
    private function facts(array $assessment): array
    {
        $readiness = $assessment['production_readiness'];

        return [
            'batch' => ListBatches::label((string) $assessment['batch_id']),
            'charge_time' => $this->local($readiness['planned_charge_at']),
            'assessment_cutoff' => $this->local($assessment['as_of']),
            'data' => 'simulated operations with real public weather and river observations',
            'readiness' => [
                'status' => $readiness['status'],
                'required_kg' => $readiness['required_quantity_kg'],
                'released_reserved_kg' => $readiness['released_reserved_quantity_kg'],
                'shortfall_kg' => $readiness['reservation_shortfall_kg'],
                'incoming_dependency_kg' => $readiness['incoming_dependency_kg'],
                'uncovered_kg' => $readiness['uncovered_quantity_kg'],
            ],
            'incoming_shipments' => array_map(fn (array $shipment): array => [
                'shipment' => $shipment['shipment_id'],
                'needed_kg' => $shipment['dependency_quantity_kg'],
                'planned_arrival' => $this->local($shipment['planned_arrival_at']),
                'arrives_after_charge' => $shipment['scheduled_after_charge'],
                'timing_status' => $shipment['status'],
                'on_time_probability' => $shipment['on_time_arrival_probability'],
                'note' => $shipment['reason'],
            ], $assessment['incoming_shipments']),
            'product_temperature' => array_map(fn (array $evidence): array => [
                'shipment' => $evidence['shipment_id'],
                'status' => $evidence['status'],
                'minutes_outside_range' => $evidence['observed_excursion_min'] === null ? null : round((float) $evidence['observed_excursion_min']),
                'budget_minutes' => $evidence['budget_min'],
                'range_c' => $evidence['range_c'],
                'last_reading_c' => $evidence['last_product_c'] === null ? null : round((float) $evidence['last_product_c'], 1),
                'readings' => $evidence['reading_count'],
                'complete_journey' => $evidence['complete_journey'],
            ], $assessment['product_temperature']),
            'model_recommendation' => [
                'action' => $assessment['recommendation']['action'],
                'reason' => $assessment['recommendation']['reason'],
                'human_qa_review_required' => $assessment['recommendation']['qa_review_required'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function template(array $facts): BatchBriefingData
    {
        $readiness = $facts['readiness'];
        $exceeded = array_values(array_filter($facts['product_temperature'], fn (array $t): bool => $t['status'] === 'OBSERVED_BUDGET_EXCEEDED'));
        $late = array_values(array_filter($facts['incoming_shipments'], fn (array $s): bool => (bool) $s['arrives_after_charge']));
        $kg = fn (mixed $value): string => mb_rtrim(mb_rtrim(number_format((float) $value, 1, '.', ''), '0'), '.').' kg';

        $headline = match (true) {
            $exceeded !== [] => sprintf('Over temperature budget: %d of %d min outside %s–%s °C', $exceeded[0]['minutes_outside_range'], $exceeded[0]['budget_minutes'], $exceeded[0]['range_c'][0], $exceeded[0]['range_c'][1]),
            $readiness['status'] === 'INSUFFICIENT_SUPPLY' => sprintf('Not enough material: %s not covered', $kg($readiness['uncovered_kg'])),
            $readiness['status'] === 'INCOMING_DEPENDENT' => sprintf('%s depends on an incoming shipment', $kg($readiness['incoming_dependency_kg'])),
            default => sprintf('Released stock covers the %s needed', $kg($readiness['required_kg'])),
        };

        $sentences = [sprintf(
            '%s charges at %s and needs %s; %s of released stock is reserved for it.',
            $facts['batch'], $facts['charge_time'], $kg($readiness['required_kg']), $kg($readiness['released_reserved_kg']),
        )];

        foreach ($late as $shipment) {
            $sentences[] = sprintf('%s of it comes on a shipment planned to arrive %s, after the charge time.', $kg($shipment['needed_kg']), $shipment['planned_arrival']);
        }

        foreach ($exceeded as $evidence) {
            $sentences[] = sprintf('The lot spent %d of its %d allowed minutes outside %s–%s °C; the last reading was %s °C.', $evidence['minutes_outside_range'], $evidence['budget_minutes'], $evidence['range_c'][0], $evidence['range_c'][1], $evidence['last_reading_c']);
        }

        $action = $facts['model_recommendation']['action'];
        $sentences[] = $action === null ? 'The model supports no action from this evidence; the decision is yours.' : sprintf('The model recommends %s; the decision is yours.', $action);

        return new BatchBriefingData(
            headline: $headline,
            summary: implode(' ', $sentences),
            qaNote: $exceeded !== [] ? 'QA must review the temperature evidence before this lot is used; quarantine hold and transport logistics remain separate lanes, and release stays a separate human decision.' : null,
            source: 'template',
            model: null,
        );
    }

    private function local(mixed $iso): ?string
    {
        return is_string($iso) ? CarbonImmutable::parse($iso)->tz(self::TIMEZONE)->format('j M H:i') : null;
    }
}
