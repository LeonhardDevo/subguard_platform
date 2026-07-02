<?php

namespace Database\Seeders;

use App\Models\GlobalSetting;
use Illuminate\Database\Seeder;

class GlobalSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'platform_commission_rate',
                'value' => '1.0',
                'description' => 'Platform commission rate percentage (e.g. 1.0 = 1%)',
            ],
            [
                'key' => 'default_free_tier_limit',
                'value' => '50',
                'description' => 'Default maximum active subscribers for users on free tier',
            ],
            [
                'key' => 'maintenance_mode',
                'value' => 'false',
                'description' => 'Toggle system-wide maintenance mode for owners API requests',
            ],
            [
                'key' => 'stripe_mock_mode',
                'value' => 'false',
                'description' => 'Toggle mocking of Stripe SDK connections',
            ],
        ];

        foreach ($settings as $setting) {
            GlobalSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'description' => $setting['description']
                ]
            );
        }
    }
}
