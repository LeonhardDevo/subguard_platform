<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        \DB::table('settings')->insert([
            ['key' => 'commission_rate', 'value' => '0.01', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'default_subscriber_limit', 'value' => '50', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'maintenance_mode', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'stripe_connect_enabled', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'allow_manual_subscriptions', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
