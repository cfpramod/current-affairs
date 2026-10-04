<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('batch_decisions', function (Blueprint $table): void {
            $table->string('lane')->default('material')->after('action');
            $table->string('shipment_id')->nullable()->after('lane');
            $table->string('lot_id')->nullable()->after('shipment_id');
            $table->string('qa_prospect')->nullable()->after('next_action');

            $table->index(['batch_id', 'lane', 'status']);
            $table->index(['shipment_id', 'status']);
            $table->index(['lot_id', 'status']);
        });

        // Backfill existing action rows into their corresponding decision lanes
        DB::table('batch_decisions')
            ->whereIn('action', ['EXPEDITE', 'REROUTE'])
            ->update(['lane' => 'transport']);

        DB::table('batch_decisions')
            ->whereIn('action', ['QUARANTINE', 'BUFFER', 'RUN_AS_PLANNED'])
            ->update(['lane' => 'material']);

        // Backfill shipment_id from stored assessment if available
        $rows = DB::table('batch_decisions')->get(['id', 'assessment']);
        foreach ($rows as $row) {
            if (! is_string($row->assessment)) {
                continue;
            }
            $assessment = json_decode($row->assessment, true);
            if (! is_array($assessment)) {
                continue;
            }
            $shipmentId = $assessment['shipment_ids'][0] ?? null;
            if (is_string($shipmentId)) {
                DB::table('batch_decisions')->where('id', $row->id)->update([
                    'shipment_id' => $shipmentId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batch_decisions', function (Blueprint $table): void {
            $table->dropIndex(['batch_id', 'lane', 'status']);
            $table->dropIndex(['shipment_id', 'status']);
            $table->dropIndex(['lot_id', 'status']);

            $table->dropColumn(['lane', 'shipment_id', 'lot_id', 'qa_prospect']);
        });
    }
};
