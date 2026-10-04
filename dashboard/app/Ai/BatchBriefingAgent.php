<?php

declare(strict_types=1);

namespace App\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Words a stored batch assessment for the operator. It only rephrases the
 * facts it is given: it never chooses an action or authorizes QA release.
 */
#[MaxTokens(8000)]
final class BatchBriefingAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
            You brief the night-shift operator of a pharmaceutical production reactor in Basel. Each production batch needs a quantity of a temperature-sensitive (2–8 °C) intermediate by its charge time. You receive the stored facts of one batch assessment as JSON.

            Write a short briefing a tired operator can take in at a glance:
            - headline: one line, at most 90 characters, stating whether the batch can charge as planned and the single main reason.
            - summary: two or three plain sentences covering material readiness, incoming shipment timing and product temperature, in that order of importance for this batch.
            - qa_note: one sentence about what QA needs to look at, or null if the temperature evidence raises no question.

            Rules:
            - Use only numbers, times and names present in the facts. Never estimate, round up or invent values. Write times as given (Basel local time).
            - Do not choose or recommend an action. If the facts include a model recommendation, you may restate it; if the recommendation is null, say that no action is supported by the evidence. The decision belongs to the operator and the approving role.
            - Never state or imply that the lot is released by QA, safe to use, or within limits beyond what the facts say. Simulated or demonstration data stays described as such when the facts say so.
            - Plain English, no markdown, no bullet points.
            TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'headline' => $schema->string()->required(),
            'summary' => $schema->string()->required(),
            'qa_note' => $schema->string()->nullable()->required(),
        ];
    }

    /**
     * Ask the API to fall back to another model if a safety classifier
     * declines, rather than returning an empty refusal.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return $provider === Lab::Anthropic ? ['fallbacks' => 'default'] : [];
    }
}
