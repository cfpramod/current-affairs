<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\BatchReviewInputData;
use App\Enums\QaProspect;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewBatchDecisionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'accept' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:1000'],
            'next_action' => ['nullable', 'string', 'max:500'],
            'responsibility_confirmed' => ['boolean'],
            'qa_prospect' => ['nullable', Rule::enum(QaProspect::class)],
        ];
    }

    public function toData(): BatchReviewInputData
    {
        $nextAction = $this->string('next_action')->trim()->toString();
        $prospect = $this->enum('qa_prospect', QaProspect::class);

        return new BatchReviewInputData(
            accept: $this->boolean('accept'),
            reason: $this->string('reason')->trim()->toString(),
            nextAction: $nextAction === '' ? null : $nextAction,
            responsibilityConfirmed: $this->boolean('responsibility_confirmed'),
            qaProspect: $prospect,
        );
    }
}
