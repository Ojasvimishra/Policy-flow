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
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Unique Client & Agent Idempotency Identifiers
            $table->string('agent_id', 64)->index();
            $table->string('client_request_id', 128);

            // Customer & Trip Intake Data
            $table->string('customer_name', 255);
            $table->date('date_of_birth');
            $table->string('destination', 32); // DOMESTIC, INTERNATIONAL
            $table->date('trip_start');
            $table->date('trip_end');
            $table->unsignedInteger('travel_days');
            $table->string('plan_code', 32);   // BASIC, PLUS, PREMIUM

            // Monetary & Multiplier Fields (Fixed Precision, Decimal-Safe)
            $table->decimal('sum_insured', 12, 2);
            $table->decimal('base_daily_rate', 10, 2);
            $table->decimal('age_multiplier', 4, 2);
            $table->decimal('destination_multiplier', 4, 2);
            $table->decimal('coverage_multiplier', 4, 2);
            $table->decimal('final_premium', 10, 2);

            // Lifecycle & Issuance State
            $table->string('status', 48)->default('RECEIVED')->index();
            $table->string('policy_number', 64)->nullable()->unique('uq_policy_requests_policy_number');
            $table->string('provider_reference', 128)->nullable()->index();
            $table->unsignedInteger('attempt_count')->default(0);

            // Safe structured metadata for diagnostics
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Compound Database Uniqueness Constraint for Intake Idempotency
            $table->unique(['agent_id', 'client_request_id'], 'uq_policy_requests_agent_client_req');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_requests');
    }
};
