<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('batch_briefings', function (Blueprint $table): void {
            $table->id();
            $table->char('assessment_id', 64)->unique();
            $table->string('batch_id');
            $table->string('model');
            $table->string('headline');
            $table->text('summary');
            $table->text('qa_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_briefings');
    }
};
