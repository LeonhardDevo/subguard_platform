<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('interval', ['monthly', 'yearly', 'weekly'])->default('monthly');
            $table->integer('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('stripe_price_id')->nullable()->unique();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
