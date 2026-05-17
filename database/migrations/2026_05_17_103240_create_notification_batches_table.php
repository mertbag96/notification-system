<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('correlation_id', 64)->index();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('accepted')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_batches');
    }
};
