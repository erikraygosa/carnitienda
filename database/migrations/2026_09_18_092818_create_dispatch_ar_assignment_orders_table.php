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
        Schema::create('dispatch_ar_assignment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_ar_assignment_id')->constrained('dispatch_ar_assignments')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['dispatch_ar_assignment_id', 'sales_order_id'], 'dispatch_ar_assign_order_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_ar_assignment_orders');
    }
};
