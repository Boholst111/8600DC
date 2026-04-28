<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->nullable(); // External invoice/OR number
            $table->enum('type', ['Revenue', 'Expense', 'Adjustment', 'Tax Settlement']);
            $table->string('category'); // e.g. Office Supplies, Logistics, Utilities
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->timestamp('transaction_date');
            $table->timestamps();

            $table->foreign('recorded_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ledgers');
    }
};
