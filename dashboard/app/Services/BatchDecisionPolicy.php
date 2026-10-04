<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\BatchDecisionData;
use App\Data\BatchReviewInputData;
use App\Enums\BatchDecisionStatus;
use App\Enums\DecisionLane;
use App\Enums\QaProspect;
use App\Enums\ShipmentAction;
use App\Enums\UserRole;
use App\Exceptions\DecisionNotAllowed;
use App\Integrations\DataEngine;
use App\Models\BatchDecision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class BatchDecisionPolicy
{
    public function __construct(private DataEngine $engine) {}

    /**
     * Resolve all shipment IDs and corresponding lot IDs for the assessment
     * using the actual operations mapping, scoped by dataset. Fails closed
     * if the operational dataset or any needed shipment mapping is missing.
     *
     * @param  array<string, mixed>  $assessment
     * @return array{shipment_ids: list<string>, lot_ids: list<string>}
     *
     * @throws DecisionNotAllowed
     */
    public function resolveShipmentsAndLots(array $assessment): array
    {
        /** @var list<string> $shipmentIds */
        $shipmentIds = array_values(array_filter($assessment['shipment_ids'] ?? [], is_string(...)));

        if ($shipmentIds === []) {
            return ['shipment_ids' => [], 'lot_ids' => []];
        }

        $datasetId = (string) ($assessment['dataset_id'] ?? '');
        $datasets = $this->engine->operations();
        $dataset = collect($datasets)->firstWhere('dataset_id', $datasetId);

        if (! is_array($dataset)) {
            throw new DecisionNotAllowed(sprintf('Operational dataset %s unavailable for lot verification.', $datasetId));
        }

        /** @var list<array{id: string, lot_id?: string}> $shipments */
        $shipments = $dataset['shipments'] ?? [];
        $shipmentsById = collect($shipments)->keyBy('id');
        $lotIds = [];

        foreach ($shipmentIds as $shipmentId) {
            $matched = $shipmentsById->get($shipmentId);

            if (! is_array($matched) || ! isset($matched['lot_id']) || ! is_string($matched['lot_id']) || $matched['lot_id'] === '') {
                throw new DecisionNotAllowed(sprintf('Shipment %s has no verified lot linkage in dataset %s.', $shipmentId, $datasetId));
            }

            $lotIds[] = $matched['lot_id'];
        }

        return [
            'shipment_ids' => array_values(array_unique($shipmentIds)),
            'lot_ids' => array_values(array_unique($lotIds)),
        ];
    }

    /**
     * Out-of-zone includes observed budget exceedance OR last sampled reading outside range.
     *
     * @param  array<string, mixed>  $assessment
     */
    public function isOutOfZone(array $assessment): bool
    {
        $temperatures = $assessment['product_temperature'] ?? [];

        foreach ($temperatures as $temp) {
            if (($temp['status'] ?? null) === 'OBSERVED_BUDGET_EXCEEDED') {
                return true;
            }

            if (($temp['last_product_outside_range'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if product temperature evidence is incomplete, unrecorded, or unavailable.
     *
     * @param  array<string, mixed>  $assessment
     */
    public function isIncompleteTemperature(array $assessment): bool
    {
        $temperatures = $assessment['product_temperature'] ?? [];

        if ($temperatures === []) {
            return true;
        }

        foreach ($temperatures as $temp) {
            if (($temp['status'] ?? null) === 'UNAVAILABLE') {
                return true;
            }

            if (($temp['reading_count'] ?? 0) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the assessment cutoff is at or past planned charge time.
     *
     * @param  array<string, mixed>  $assessment
     */
    public function isRetrospective(array $assessment): bool
    {
        $chargeAt = CarbonImmutable::parse($assessment['production_readiness']['planned_charge_at']);
        $asOf = CarbonImmutable::parse($assessment['as_of']);

        return $chargeAt->lte($asOf);
    }

    /**
     * Find active quarantine covering this batch or any of its affected shipments/lots.
     *
     * @param  array<string, mixed>  $assessment
     */
    public function findActiveQuarantine(array $assessment, string $batchId): ?BatchDecision
    {
        $linkage = $this->resolveShipmentsAndLots($assessment);
        $datasetId = (string) ($assessment['dataset_id'] ?? '');

        return BatchDecision::query()
            ->where('dataset_id', $datasetId)
            ->where('action', ShipmentAction::Quarantine)
            ->active()
            ->where(function ($query) use ($batchId, $linkage) {
                $query->where('batch_id', $batchId);

                if ($linkage['shipment_ids'] !== []) {
                    $query->orWhereIn('shipment_id', $linkage['shipment_ids']);
                }

                if ($linkage['lot_ids'] !== []) {
                    $query->orWhereIn('lot_id', $linkage['lot_ids']);
                }
            })
            ->first();
    }

    /**
     * Find accepted quarantine for this batch or any of its affected shipments/lots.
     *
     * @param  array<string, mixed>  $assessment
     */
    public function findAcceptedQuarantine(array $assessment, string $batchId): ?BatchDecision
    {
        $linkage = $this->resolveShipmentsAndLots($assessment);
        $datasetId = (string) ($assessment['dataset_id'] ?? '');

        return BatchDecision::query()
            ->where('dataset_id', $datasetId)
            ->where('action', ShipmentAction::Quarantine)
            ->where('status', BatchDecisionStatus::Accepted)
            ->where(function ($query) use ($batchId, $linkage) {
                $query->where('batch_id', $batchId);

                if ($linkage['shipment_ids'] !== []) {
                    $query->orWhereIn('shipment_id', $linkage['shipment_ids']);
                }

                if ($linkage['lot_ids'] !== []) {
                    $query->orWhereIn('lot_id', $linkage['lot_ids']);
                }
            })
            ->latest('reviewed_at')
            ->first();
    }

    /**
     * Validate an operator's decision submission against server policy gates.
     *
     * @param  array<string, mixed>  $assessment
     *
     * @throws DecisionNotAllowed
     */
    public function validateSubmission(string $batchId, User $user, ShipmentAction $action, array $assessment): void
    {
        if ($user->role !== UserRole::Operator) {
            throw new DecisionNotAllowed('Only operators submit batch decisions.');
        }

        if ($this->isRetrospective($assessment)) {
            throw new DecisionNotAllowed('The charge time has passed; retrospective assessments take no new decisions.');
        }

        $lane = $action->lane();

        // 1. Per-lane guarding: block second active request in the same lane.
        $existingLaneActive = BatchDecision::query()
            ->where('batch_id', $batchId)
            ->where('lane', $lane->value)
            ->active()
            ->exists();

        if ($existingLaneActive) {
            throw new DecisionNotAllowed(sprintf('This batch already has an open or accepted %s decision.', $lane->value));
        }

        $isOutOfZone = $this->isOutOfZone($assessment);
        $activeQuarantine = $this->findActiveQuarantine($assessment, $batchId);
        $isIncomplete = $this->isIncompleteTemperature($assessment);
        $incomingDepKg = (float) ($assessment['production_readiness']['incoming_dependency_kg'] ?? 0);

        // 2. Material lane rules
        if ($lane === DecisionLane::Material) {
            if ($action === ShipmentAction::RunAsPlanned) {
                if ($isOutOfZone) {
                    throw new DecisionNotAllowed('Observed temperature excursion requires quarantine.');
                }

                if ($activeQuarantine !== null) {
                    throw new DecisionNotAllowed('Material is quarantined; run as planned is not allowed.');
                }

                if ($incomingDepKg > 0 && $isIncomplete) {
                    throw new DecisionNotAllowed('Temperature evidence is incomplete; QA review required before running planned material.');
                }
            }

            if ($action === ShipmentAction::Buffer) {
                $readiness = $assessment['production_readiness'];
                $required = (float) ($readiness['required_quantity_kg'] ?? 0);
                $released = (float) ($readiness['released_reserved_quantity_kg'] ?? 0);

                if (($readiness['status'] ?? '') !== 'RESERVED_STOCK_SUFFICIENT' && $released < $required) {
                    throw new DecisionNotAllowed('Released safety stock is insufficient to buffer this batch.');
                }
            }
        }

        // 3. Transport lane rules
        if ($lane === DecisionLane::Transport) {
            $incomingShipments = $assessment['incoming_shipments'] ?? [];

            if ($incomingDepKg <= 0 || $incomingShipments === []) {
                throw new DecisionNotAllowed(sprintf('This batch has no incoming shipment dependency to %s.', strtolower($action->label())));
            }

            $allArrived = collect($incomingShipments)->every(fn (array $s): bool => ($s['status'] ?? null) === 'ARRIVED');
            if ($allArrived) {
                throw new DecisionNotAllowed('Incoming shipments have already arrived; cannot expedite.');
            }

            $hasUnavailable = collect($incomingShipments)->contains(fn (array $s): bool => ($s['status'] ?? null) === 'UNAVAILABLE');
            if ($hasUnavailable) {
                throw new DecisionNotAllowed('Incoming shipment timing is unavailable for transport actions.');
            }

            if ($action === ShipmentAction::Expedite) {
                if ($isOutOfZone || $activeQuarantine !== null) {
                    $acceptedQuarantine = $this->findAcceptedQuarantine($assessment, $batchId);

                    if ($acceptedQuarantine === null) {
                        throw new DecisionNotAllowed('Expedite can only be requested after QA confirms the held lot is potentially usable.');
                    }

                    if ($acceptedQuarantine->assessment_id !== $assessment['assessment_id']) {
                        throw new DecisionNotAllowed('QA assessment is from different evidence; reload before deciding.');
                    }

                    if ($acceptedQuarantine->qa_prospect === QaProspect::Rejected) {
                        throw new DecisionNotAllowed('QA rejected this lot for production; expedite is not allowed.');
                    }

                    if ($acceptedQuarantine->qa_prospect !== QaProspect::PotentiallyUsable) {
                        throw new DecisionNotAllowed('Expedite requires QA to record that the held lot is potentially usable.');
                    }
                }
            }
        }
    }

    /**
     * Revalidate all gates at review time, especially when accepting transport decisions.
     *
     * @param  array<string, mixed>  $latestAssessment
     *
     * @throws DecisionNotAllowed
     */
    public function validateReview(BatchDecision $decision, User $user, BatchReviewInputData $input, array $latestAssessment): void
    {
        if ($decision->status !== BatchDecisionStatus::Pending) {
            throw new DecisionNotAllowed('This request has already been reviewed.');
        }

        if ($user->role !== $decision->required_role) {
            throw new DecisionNotAllowed(sprintf('This request needs %s review.', $decision->required_role->label()));
        }

        if ($input->accept && (in_array($input->nextAction, [null, ''], true) || ! $input->responsibilityConfirmed)) {
            throw new DecisionNotAllowed('To accept, state the next action and confirm your team will take it.');
        }

        if (! $input->accept) {
            return;
        }

        // Revalidate on latest assessment evidence
        if ($latestAssessment['assessment_id'] !== $decision->assessment_id) {
            throw new DecisionNotAllowed('Assessment evidence has changed; cannot approve on stale evidence.');
        }

        if ($this->isRetrospective($latestAssessment)) {
            throw new DecisionNotAllowed('The charge time has passed; retrospective assessments cannot be accepted.');
        }

        // Transport approval revalidates held lot status and QA prospect
        if ($decision->lane === DecisionLane::Transport && $decision->action === ShipmentAction::Expedite) {
            $isOutOfZone = $this->isOutOfZone($latestAssessment);
            $activeQuarantine = $this->findActiveQuarantine($latestAssessment, $decision->batch_id);

            if ($isOutOfZone || $activeQuarantine !== null) {
                $acceptedQuarantine = $this->findAcceptedQuarantine($latestAssessment, $decision->batch_id);

                if ($acceptedQuarantine === null || $acceptedQuarantine->assessment_id !== $latestAssessment['assessment_id']) {
                    throw new DecisionNotAllowed('Expedite requires QA confirmation of preliminary usability on current evidence.');
                }

                if ($acceptedQuarantine->qa_prospect !== QaProspect::PotentiallyUsable) {
                    throw new DecisionNotAllowed('Cannot accept expedite: held lot does not have a positive preliminary QA prospect.');
                }
            }
        }
    }

    /**
     * Evaluate console state flags consistently for UI and presentation DTOs.
     *
     * @param  array<string, mixed>  $assessment
     * @param  Collection<int, BatchDecision>  $decisions
     * @return array{
     *     activeMaterialDecision: ?BatchDecisionData,
     *     activeTransportDecision: ?BatchDecisionData,
     *     activeDecision: ?BatchDecisionData,
     *     qaProspect: ?QaProspect,
     *     materialHeld: bool,
     *     expediteAllowed: bool,
     *     expediteBlockedReason: ?string,
     *     bufferAllowed: bool,
     *     bufferBlockedReason: ?string,
     *     runAllowed: bool,
     *     runBlockedReason: ?string
     * }
     */
    public function evaluateConsoleState(array $assessment, string $batchId, Collection $decisions): array
    {
        $activeMaterial = $decisions->first(
            fn (BatchDecision $d): bool => $d->lane === DecisionLane::Material && $d->status !== BatchDecisionStatus::Denied
        );
        $activeTransport = $decisions->first(
            fn (BatchDecision $d): bool => $d->lane === DecisionLane::Transport && $d->status !== BatchDecisionStatus::Denied
        );
        $active = $activeTransport ?? $activeMaterial ?? null;

        $isOutOfZone = $this->isOutOfZone($assessment);
        $activeQuarantine = $this->findActiveQuarantine($assessment, $batchId);
        $acceptedQuarantine = $this->findAcceptedQuarantine($assessment, $batchId);
        $qaProspect = ($acceptedQuarantine?->assessment_id === $assessment['assessment_id'])
            ? $acceptedQuarantine->qa_prospect
            : null;

        $materialHeld = ($activeMaterial?->action === ShipmentAction::Quarantine)
            || ($activeQuarantine !== null)
            || $isOutOfZone;

        $isRetrospective = $this->isRetrospective($assessment);
        $incomingDepKg = (float) ($assessment['production_readiness']['incoming_dependency_kg'] ?? 0);
        $incomingShipments = $assessment['incoming_shipments'] ?? [];
        $isIncomplete = $this->isIncompleteTemperature($assessment);

        // Run as planned evaluation
        $runAllowed = ! $isRetrospective && ! $materialHeld && ! ($incomingDepKg > 0 && $isIncomplete);
        $runBlockedReason = match (true) {
            $isRetrospective => 'Retrospective assessment.',
            $materialHeld => 'Material is held / out of zone.',
            $incomingDepKg > 0 && $isIncomplete => 'Incomplete temperature evidence requires QA review.',
            default => null,
        };

        // Buffer evaluation
        $readiness = $assessment['production_readiness'];
        $required = (float) ($readiness['required_quantity_kg'] ?? 0);
        $released = (float) ($readiness['released_reserved_quantity_kg'] ?? 0);
        $bufferStockSufficient = ($readiness['status'] ?? '') === 'RESERVED_STOCK_SUFFICIENT' || $released >= $required;
        $bufferAllowed = ! $isRetrospective && $bufferStockSufficient;
        $bufferBlockedReason = match (true) {
            $isRetrospective => 'Retrospective assessment.',
            ! $bufferStockSufficient => 'Insufficient released replacement stock.',
            default => null,
        };

        // Expedite evaluation
        $hasDependency = $incomingDepKg > 0 && $incomingShipments !== [];
        $timingAvailable = $hasDependency && ! collect($incomingShipments)->contains(fn (array $s): bool => ($s['status'] ?? null) === 'UNAVAILABLE')
            && ! collect($incomingShipments)->every(fn (array $s): bool => ($s['status'] ?? null) === 'ARRIVED');

        $expediteAllowed = ! $isRetrospective && $timingAvailable && (! $materialHeld || $qaProspect === QaProspect::PotentiallyUsable);
        $expediteBlockedReason = match (true) {
            $isRetrospective => 'Retrospective assessment.',
            ! $hasDependency => 'No incoming shipment dependency.',
            ! $timingAvailable => 'Shipment timing unavailable or arrived.',
            ! $materialHeld => null,
            $activeMaterial?->status === BatchDecisionStatus::Pending => 'Quarantine is pending QA review.',
            $qaProspect === QaProspect::Rejected => 'QA rejected this lot for production; expedite spend is blocked.',
            $qaProspect !== QaProspect::PotentiallyUsable => 'Held lot requires QA preliminary prospect assessment before requesting expedite.',
            default => null,
        };

        return [
            'activeMaterialDecision' => $activeMaterial === null ? null : BatchDecisionData::fromModel($activeMaterial),
            'activeTransportDecision' => $activeTransport === null ? null : BatchDecisionData::fromModel($activeTransport),
            'activeDecision' => $active === null ? null : BatchDecisionData::fromModel($active),
            'qaProspect' => $qaProspect,
            'materialHeld' => $materialHeld,
            'expediteAllowed' => $expediteAllowed,
            'expediteBlockedReason' => $expediteBlockedReason,
            'bufferAllowed' => $bufferAllowed,
            'bufferBlockedReason' => $bufferBlockedReason,
            'runAllowed' => $runAllowed,
            'runBlockedReason' => $runBlockedReason,
        ];
    }
}
