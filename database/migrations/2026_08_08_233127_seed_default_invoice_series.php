<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * invoice_series estaba vacía en producción (mismo patrón que
     * pac_configurations: se creó a mano en local, nunca se migró/sembró
     * en prod). Sin una serie "es_default" para tipo I, InvoiceController::create()
     * siempre ofrece folio=1 sin importar cuántas facturas ya existan, y al
     * guardar revienta con "Duplicate entry 'A-1'" en cuanto ya hay una factura
     * con ese folio. Se siembra serie A por defecto para I, E, P y N,
     * arrancando folio_actual en el máximo folio ya usado en `invoices` para
     * no chocar con facturas existentes.
     */
    public function up(): void
    {
        $company = DB::table('companies')->where('activo', true)->first();
        if (! $company) {
            $company = DB::table('companies')->first();
        }
        if (! $company) {
            return; // no hay empresa, nada que sembrar todavía
        }

        foreach (['I', 'E', 'P', 'N'] as $tipo) {
            $exists = DB::table('invoice_series')
                ->where('company_id', $company->id)
                ->where('tipo_comprobante', $tipo)
                ->exists();

            if ($exists) {
                continue;
            }

            $maxFolio = (int) DB::table('invoices')
                ->where('tipo_comprobante', $tipo)
                ->where('serie', 'A')
                ->max('folio');

            DB::table('invoice_series')->insert([
                'company_id'       => $company->id,
                'serie'            => 'A',
                'tipo_comprobante' => $tipo,
                'folio_actual'     => $maxFolio,
                'folio_inicio'     => 1,
                'activa'           => true,
                'es_default'       => true,
                'descripcion'      => 'Serie por defecto (creada por migración)',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('invoice_series')
            ->where('serie', 'A')
            ->where('descripcion', 'Serie por defecto (creada por migración)')
            ->delete();
    }
};
