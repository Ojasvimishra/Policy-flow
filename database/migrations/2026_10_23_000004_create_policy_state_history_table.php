<?php

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
        Schema::create('policy_state_history', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Referential Integrity to Policy Request
            $table->foreignId('policy_request_id')
                ->constrained('policy_requests')
                ->cascadeOnDelete();

            $table->string('from_state', 48);
            $table->string('to_state', 48);
            $table->string('trigger', 64); // INTAKE_VALIDATION, SUBMIT_API, PROVIDER_CALLBACK, RECONCILIATION, MANUAL_RETRY
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();

            // Append-Only Audit Trail Timestamp (No updated_at)
            $table->timestamp('created_at')->useCurrent();

            // Compound Index for Chronological Audit Trail Queries
            $table->index(['policy_request_id', 'created_at'], 'idx_policy_state_history_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_state_history');
    }
};
