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
        Schema::create('provider_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('provider', 64)->default('mock_insurer');
            $table->string('event_id', 128);
            $table->string('event_type', 64);

            // Optional link to local PolicyRequest once reconciled/associated
            $table->foreignId('policy_request_id')
                ->nullable()
                ->constrained('policy_requests')
                ->nullOnDelete();

            $table->string('provider_reference', 128)->nullable()->index();
            $table->json('payload');

            $table->boolean('processed')->default(false)->index();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // Strict Provider-Scoped Event Deduplication
            $table->unique(['provider', 'event_id'], 'uq_provider_events_provider_event');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_events');
    }
};
