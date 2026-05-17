<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->nullable()->constrained('notification_batches')->nullOnDelete();
            $table->foreignUuid('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->string('channel', 16);
            $table->string('priority', 16);
            $table->string('status', 24);
            $table->string('recipient');
            $table->longText('content');
            $table->json('payload')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('correlation_id', 64)->index();
            $table->timestamps();

            $table->index(['status', 'channel']);
            $table->index('scheduled_at');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
