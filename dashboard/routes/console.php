<?php

declare(strict_types=1);

use App\Actions\Batches\PrepareBatchBriefings;
use App\Actions\Batches\ResetBatchDecisions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:reset', function (ResetBatchDecisions $reset): void {
    $this->info(sprintf('Cleared %d batch decision(s).', $reset->handle()));
})->purpose('Clear all batch decisions before a demo rehearsal');

Artisan::command('batches:brief', function (PrepareBatchBriefings $prepare): void {
    foreach ($prepare->handle() as $batchId => $source) {
        $this->line(sprintf('%s: %s', $batchId, $source === 'llm' ? 'AI briefing' : 'template (no API key or provider error)'));
    }
})->purpose('Generate and store batch briefings before a demo');
