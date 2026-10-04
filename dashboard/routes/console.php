<?php

declare(strict_types=1);

use App\Actions\Batches\ResetBatchDecisions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:reset', function (ResetBatchDecisions $reset): void {
    $this->info(sprintf('Cleared %d batch decision(s).', $reset->handle()));
})->purpose('Clear all batch decisions before a demo rehearsal');
