<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'stripe_connected', 'completed'])->default('pending');
            $table->string('stripe_account_id')->nullable()->unique();
            $table->string('stripe_account_email')->nullable();
            $table->boolean('stripe_onboarding_complete')->default(false);
            $table->boolean('stripe_capabilities_enabled')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboardings');
    }
};
