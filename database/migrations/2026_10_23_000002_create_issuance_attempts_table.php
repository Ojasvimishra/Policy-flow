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
        Schema::create('issuance_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Referential Integrity to Parent Policy Request
            $table->foreignId('policy_request_id')
                ->constrained('policy_requests')
                ->cascadeOnDelete();

            $table->unsignedInteger('attempt_number');
            $table->string('provider', 64)->default('mock_insurer');
            $table->string('scenario_requested', 64)->nullable();

            // Safe structured payloads (redacted of credentials)
            $table->json('request_payload');
            $table->integer('response_status_code')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_detail')->nullable();

            // Attempt Outcome Status
            $table->string('status', 32)->default('IN_FLIGHT')->index();
            $table->timestamp('attempted_at');
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Unique Attempt Sequence per Policy Request
            $table->unique(['policy_request_id', 'attempt_number'], 'uq_issuance_attempts_policy_attempt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issuance_attempts');
    }
};
