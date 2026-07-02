<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('global_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed default values
        \DB::table('global_settings')->insert([
            ['key' => 'platform_commission_rate', 'value' => '1.0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'default_free_tier_limit', 'value' => '50', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'maintenance_mode', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'stripe_mock_mode', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('global_settings');
    }
};
