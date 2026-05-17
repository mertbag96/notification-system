<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('status', 24);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('provider_response')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['notification_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
    }
};
