<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table tracks webhook calls to prevent duplicate processing (idempotency).
     *
     * @return void
     */
    public function up()
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50)->index(); // e.g., 'barion.payment'
            $table->string('external_id', 64)->index(); // Barion PaymentId
            $table->string('event_signature', 64)->unique(); // Hash of event + timestamp
            $table->string('source_ip', 45); // IPv4 or IPv6
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->text('request_data')->nullable(); // JSON of webhook payload
            $table->text('response_data')->nullable(); // Result of processing
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Index for cleanup queries
            $table->index('created_at');
            
            // Composite index for faster duplicate detection
            $table->index(['event_type', 'external_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('webhook_events');
    }
};