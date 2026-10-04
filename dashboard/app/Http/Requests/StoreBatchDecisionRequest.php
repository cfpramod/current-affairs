<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\BatchDecisionInputData;
use App\Enums\ShipmentAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBatchDecisionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assessment_id' => ['required', 'string', 'size:64'],
            'action' => ['required', Rule::enum(ShipmentAction::class)],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function toData(): BatchDecisionInputData
    {
        return new BatchDecisionInputData(
            assessmentId: $this->string('assessment_id')->toString(),
            action: $this->enum('action', ShipmentAction::class) ?? ShipmentAction::RunAsPlanned,
            reason: $this->string('reason')->trim()->toString(),
        );
    }
}
