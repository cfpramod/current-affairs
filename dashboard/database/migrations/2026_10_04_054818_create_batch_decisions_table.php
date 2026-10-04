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
        Schema::create('batch_decisions', function (Blueprint $table): void {
            $table->id();
            $table->string('dataset_id');
            $table->string('batch_id');
            $table->char('assessment_id', 64);
            $table->timestamp('assessment_as_of');
            $table->json('assessment');
            $table->string('model_action')->nullable();
            $table->string('action');
            $table->string('required_role');
            $table->string('status');
            $table->foreignId('submitted_by')->constrained('users');
            $table->text('submitted_reason');
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('review_reason')->nullable();
            $table->text('next_action')->nullable();
            $table->boolean('responsibility_confirmed')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'submitted_at']);
            $table->index(['status', 'required_role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_decisions');
    }
};
