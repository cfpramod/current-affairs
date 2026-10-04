<?php

declare(strict_types=1);

namespace App\Actions\Batches;

use App\Data\AnswerData;
use App\Data\AnswersData;

/**
 * Turns a stored engine assessment into the operator's two questions:
 * will the material be ready for the charge, and is it still good to use?
 */
final class SummarizeBatchAssessment
{
    /**
     * @param  array<string, mixed>  $assessment
     */
    public function handle(array $assessment): AnswersData
    {
        /** @var array<string, mixed> $readiness */
        $readiness = $assessment['production_readiness'];
        /** @var list<array<string, mixed>> $temperatures */
        $temperatures = $assessment['product_temperature'];

        return new AnswersData(
            arrival: $this->readiness($readiness),
            quality: $this->quality($temperatures),
        );
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    private function readiness(array $readiness): AnswerData
    {
        $required = $this->kg($readiness['required_quantity_kg']);
        $reserved = $this->kg($readiness['released_reserved_quantity_kg']);
        $incoming = $this->kg($readiness['incoming_dependency_kg']);
        $uncovered = $this->kg($readiness['uncovered_quantity_kg']);

        return match ($readiness['status']) {
            'RESERVED_STOCK_SUFFICIENT' => new AnswerData('Material ready for the charge?', 'yes', sprintf('Released stock reserved: %s of %s', $reserved, $required)),
            'INCOMING_DEPENDENT' => new AnswerData('Material ready for the charge?', 'at_risk', sprintf('%s of %s reserved · %s depends on an incoming shipment', $reserved, $required, $incoming)),
            default => new AnswerData('Material ready for the charge?', 'no', sprintf('%s of %s reserved · %s not covered', $reserved, $required, $uncovered)),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $temperatures
     */
    private function quality(array $temperatures): AnswerData
    {
        $question = 'Still good to use?';
        $exceeded = array_values(array_filter($temperatures, fn (array $t): bool => $t['status'] === 'OBSERVED_BUDGET_EXCEEDED'));

        if ($exceeded !== []) {
            return new AnswerData($question, 'no', sprintf(
                '%d of %d min outside %s–%s °C · QA review needed',
                (int) round((float) $exceeded[0]['observed_excursion_min']),
                (int) $exceeded[0]['budget_min'],
                $exceeded[0]['range_c'][0],
                $exceeded[0]['range_c'][1],
            ));
        }

        $observed = array_filter($temperatures, fn (array $t): bool => $t['status'] === 'OBSERVED_WITHIN_BUDGET');

        if ($temperatures !== [] && count($observed) === count($temperatures)) {
            $allComplete = collect($temperatures)->every(fn (array $t): bool => ($t['complete_journey'] ?? false) === true);
            if ($allComplete) {
                return new AnswerData($question, 'yes', 'Sampled readings within the excursion budget · QA release required');
            }

            return new AnswerData($question, 'yes', 'Within budget so far · Journey in transit · QA review required');
        }

        return new AnswerData($question, 'unknown', 'No product-temperature readings yet');
    }

    private function kg(mixed $value): string
    {
        return mb_rtrim(mb_rtrim(number_format((float) $value, 1, '.', ''), '0'), '.').' kg';
    }
}
