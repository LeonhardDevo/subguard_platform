<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('commission_amount', 10, 2)->default(0)->after('amount');
            $table->decimal('commission_rate', 5, 4)->default(0.0100)->after('commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'commission_rate']);
        });
    }
};
