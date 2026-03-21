<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('shipping_route_id')->nullable()->after('price_list_id');
            $table->string('payment_method', 30)->default('EFECTIVO')->after('moneda');
            $table->unsignedSmallInteger('credit_days')->nullable()->after('payment_method');
            $table->string('delivery_type', 20)->default('ENVIO')->after('credit_days');
            $table->string('entrega_nombre',   255)->nullable()->after('delivery_type');
            $table->string('entrega_telefono',  50)->nullable()->after('entrega_nombre');
            $table->string('entrega_calle',    255)->nullable()->after('entrega_telefono');
            $table->string('entrega_numero',    50)->nullable()->after('entrega_calle');
            $table->string('entrega_colonia',  255)->nullable()->after('entrega_numero');
            $table->string('entrega_ciudad',   255)->nullable()->after('entrega_colonia');
            $table->string('entrega_estado',   255)->nullable()->after('entrega_ciudad');
            $table->string('entrega_cp',        10)->nullable()->after('entrega_estado');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_route_id',
                'payment_method', 'credit_days', 'delivery_type',
                'entrega_nombre', 'entrega_telefono', 'entrega_calle',
                'entrega_numero', 'entrega_colonia', 'entrega_ciudad',
                'entrega_estado', 'entrega_cp',
            ]);
        });
    }
};