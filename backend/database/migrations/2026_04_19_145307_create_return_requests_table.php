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
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Return metadata
            $table->string('reason');          // 'Defective', 'Wrong Item', 'Not As Described', 'Damaged in Transit', 'Changed Mind', 'Other'
            $table->text('description')->nullable();    // Client's detailed explanation
            $table->string('evidence_photo')->nullable(); // Uploaded proof image path

            // Items being returned (JSON array of {order_item_id, qty, reason})
            $table->json('items')->nullable();

            // Lifecycle
            $table->string('status')->default('Pending');
            // Statuses: Pending, Under Review, Approved, Rejected, Item Received, Resolved

            // Admin resolution
            $table->string('resolution')->nullable();
            // Resolutions: Refund, Exchange, Store Credit, Reject
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
