<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('external_id')->nullable();
            $table->string('stripe_customer_id')->nullable()->unique();
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('email');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
