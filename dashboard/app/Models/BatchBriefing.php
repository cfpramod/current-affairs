<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * An LLM-worded briefing, stored once per immutable engine assessment.
 *
 * @property-read int $id
 * @property string $assessment_id
 * @property string $batch_id
 * @property string $model
 * @property string $source
 * @property string $headline
 * @property string $summary
 * @property ?string $qa_note
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class BatchBriefing extends Model {}
