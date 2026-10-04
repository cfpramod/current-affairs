<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who worded a batch briefing. Only Llm and Codex briefings are stored; the
 * template is built from the evidence on demand.
 */
enum BriefingSource: string
{
    case Llm = 'llm';
    case Codex = 'codex';
    case Template = 'template';

    public function label(): string
    {
        return match ($this) {
            self::Llm => 'AI briefing',
            self::Codex => 'Codex briefing',
            self::Template => 'template (AI unavailable)',
        };
    }
}
