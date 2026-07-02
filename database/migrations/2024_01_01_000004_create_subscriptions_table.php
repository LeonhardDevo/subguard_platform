<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('restrict');
            $table->enum('status', ['active', 'canceled', 'past_due', 'trialing', 'paused', 'incomplete'])->default('active');
            $table->date('start_date');
            $table->date('next_billing_date')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('status');
            $table->index('next_billing_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
