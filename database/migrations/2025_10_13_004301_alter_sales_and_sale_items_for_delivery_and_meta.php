<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Identificación y moneda
            $table->string('folio')->nullable()->after('id')->index();
            $table->string('moneda', 10)->default('MXN')->after('fecha');

            // Lista de precios y crédito
            $table->foreignId('price_list_id')->nullable()->after('client_id')
                ->constrained('price_lists')->nullOnDelete();
            $table->unsignedInteger('credit_days')->nullable()->after('tipo_venta');

            // Tipo de entrega + dirección
            $table->enum('delivery_type', ['ENVIO','RECOGER'])->default('RECOGER')->after('tipo_venta');
            $table->string('entrega_nombre')->nullable()->after('delivery_type');
            $table->string('entrega_telefono', 50)->nullable()->after('entrega_nombre');
            $table->string('entrega_calle')->nullable()->after('entrega_telefono');
            $table->string('entrega_numero', 50)->nullable()->after('entrega_calle');
            $table->string('entrega_colonia')->nullable()->after('entrega_numero');
            $table->string('entrega_ciudad')->nullable()->after('entrega_colonia');
            $table->string('entrega_estado')->nullable()->after('entrega_ciudad');
            $table->string('entrega_cp', 10)->nullable()->after('entrega_estado');

            // Ruta de envío (si usas rutas)
            $table->foreignId('shipping_route_id')->nullable()->after('driver_id')
                ->constrained('shipping_routes')->nullOnDelete();

            // CFDI (opcional)
            $table->string('cfdi_uuid', 40)->nullable()->after('status')->index();
            $table->string('cfdi_xml_path')->nullable()->after('cfdi_uuid');
            $table->string('cfdi_pdf_path')->nullable()->after('cfdi_xml_path');
            $table->timestamp('stamped_at')->nullable()->after('cfdi_pdf_path');
            $table->timestamp('canceled_at')->nullable()->after('stamped_at');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('descripcion', 255)->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'folio','moneda','price_list_id','credit_days',
                'delivery_type','entrega_nombre','entrega_telefono','entrega_calle','entrega_numero',
                'entrega_colonia','entrega_ciudad','entrega_estado','entrega_cp',
                'shipping_route_id','cfdi_uuid','cfdi_xml_path','cfdi_pdf_path','stamped_at','canceled_at',
            ]);
        });
    }
};
