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
        Schema::create('stock_transfers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('from_warehouse_id')->constrained('warehouses');
    $table->foreignId('to_warehouse_id')->constrained('warehouses');
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->date('fecha')->index();
    $table->enum('status', ['draft','completed','cancelled'])->default('completed'); // si quieres flujo, pon 'draft'
    $table->text('notas')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
