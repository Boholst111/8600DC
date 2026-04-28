<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('store_credit_used', 15, 2)->default(0)->after('total_amount');
            $table->decimal('balance_due', 15, 2)->default(0)->after('store_credit_used');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['store_credit_used', 'balance_due']);
        });
    }
};
