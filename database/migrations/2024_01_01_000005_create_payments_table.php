<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['succeeded', 'failed', 'pending', 'refunded'])->default('pending');
            $table->string('provider')->default('stripe');
            $table->string('provider_payment_id')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'status']);
            $table->index('status');
            $table->index('provider_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
