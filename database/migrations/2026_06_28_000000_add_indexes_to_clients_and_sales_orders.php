<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->index('nombre');
            $table->index('activo');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['nombre']);
            $table->dropIndex(['activo']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
        });
    }
};
