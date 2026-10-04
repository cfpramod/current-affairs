<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_briefings', function (Blueprint $table): void {
            $table->string('source')->default('llm');
        });
    }

    public function down(): void
    {
        Schema::table('batch_briefings', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
