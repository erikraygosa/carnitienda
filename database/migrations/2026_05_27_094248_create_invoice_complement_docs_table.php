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
        Schema::create('invoice_complement_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('related_invoice_id')->constrained('invoices');
            $table->unsignedSmallInteger('num_parcialidad');
            $table->decimal('imp_saldo_anterior', 14, 2);
            $table->decimal('imp_pagado', 14, 2);
            $table->decimal('imp_saldo_insoluto', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_complement_docs');
    }
};
