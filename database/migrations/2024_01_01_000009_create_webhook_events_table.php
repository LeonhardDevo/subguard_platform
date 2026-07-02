<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('stripe');
            $table->string('provider_event_id')->nullable()->unique();
            $table->string('event_type');
            $table->json('payload');
            $table->enum('status', ['received', 'processing', 'processed', 'failed'])->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();

            $table->index('event_type');
            $table->index('status');
            $table->index('provider_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
