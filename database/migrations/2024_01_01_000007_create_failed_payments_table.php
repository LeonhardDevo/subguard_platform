<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->text('reason')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'resolved_at']);
            $table->index('next_retry_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_payments');
    }
};
