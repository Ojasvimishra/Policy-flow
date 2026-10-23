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
        Schema::create('mock_insurer_policies', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Provider Identity & Local Request Mapping
            $table->string('client_request_id', 128)->unique('uq_mock_insurer_client_req');
            $table->string('agent_id', 64)->index();

            // Unique Provider References
            $table->string('policy_number', 64)->unique('uq_mock_insurer_policy_number');
            $table->string('provider_reference', 128)->unique('uq_mock_insurer_provider_ref');

            // Status and Financials
            $table->string('status', 32)->default('ISSUED')->index();
            $table->decimal('sum_insured', 12, 2);
            $table->decimal('premium_charged', 10, 2);

            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_insurer_policies');
    }
};
