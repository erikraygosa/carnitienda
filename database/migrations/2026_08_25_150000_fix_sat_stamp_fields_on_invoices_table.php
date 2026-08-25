<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * xml_timbrado estaba definida como columna JSON, pero lo que se guarda
     * ahí es el XML crudo del CFDI timbrado — MySQL rechaza silenciosamente
     * (o trunca a NULL vía el mapeo del driver) cualquier valor que no sea
     * JSON válido. Se cambia a LONGTEXT para poder guardar el XML tal cual.
     *
     * rfc_provider_cert: RFC del PAC/proveedor de certificación que timbró
     * el CFDI (dato que exige la representación impresa junto a los sellos).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->longText('xml_timbrado')->nullable()->change();

            if (! Schema::hasColumn('invoices', 'rfc_provider_cert')) {
                $table->string('rfc_provider_cert', 20)->nullable()->after('numero_certificado_sat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'rfc_provider_cert')) {
                $table->dropColumn('rfc_provider_cert');
            }
            $table->json('xml_timbrado')->nullable()->change();
        });
    }
};
