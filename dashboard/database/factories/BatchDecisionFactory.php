<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BatchDecisionStatus;
use App\Enums\ShipmentAction;
use App\Enums\UserRole;
use App\Models\BatchDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatchDecision>
 */
final class BatchDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dataset_id' => 'operations-demo-v1',
            'batch_id' => 'operations-demo-v1-batch-002',
            'assessment_id' => hash('sha256', fake()->uuid()),
            'assessment_as_of' => now(),
            'assessment' => [],
            'model_action' => null,
            'action' => ShipmentAction::Quarantine,
            'required_role' => UserRole::Qa,
            'status' => BatchDecisionStatus::Pending,
            'submitted_by' => User::factory(),
            'submitted_reason' => fake()->sentence(),
            'submitted_at' => now(),
        ];
    }
}
