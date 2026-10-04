<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Batch briefings
    |--------------------------------------------------------------------------
    |
    | A short plain-language summary of each stored batch assessment. With an
    | API key the wording comes from an LLM, which only rephrases the stored
    | evidence; without one, or on provider error, opt-in local Codex is
    | tried before the evidence template. Prepare and cache before presenting.
    |
    */

    'enabled' => filled(env('ANTHROPIC_API_KEY')) && env('SMARTFLOW_AI_BRIEFINGS', true),

    'provider' => env('SMARTFLOW_AI_PROVIDER', 'anthropic'),

    'model' => env('SMARTFLOW_AI_MODEL', 'claude-opus-5-5'),

    'timeout' => (int) env('SMARTFLOW_AI_TIMEOUT', 30),

    'codex' => [
        'enabled' => (bool) env('SMARTFLOW_CODEX_BRIEFINGS', false),
        'binary' => env('SMARTFLOW_CODEX_BINARY', 'codex'),
        'model' => env('SMARTFLOW_CODEX_MODEL'),
        'timeout' => (int) env('SMARTFLOW_CODEX_TIMEOUT', 90),
    ],

];
