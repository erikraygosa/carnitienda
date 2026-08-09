<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Facturas, Notas de Crédito y Complementos de Pago compartían la misma
     * serie "A" (sembrada junto en 2026_08_08_233127). Se separan con series
     * propias: NC para Egreso, CP para Pago. La serie "A" existente para
     * esos tipos se desmarca como default pero no se borra (ya tiene
     * documentos reales emitidos y no se pueden reutilizar sus folios).
     */
    public function up(): void
    {
        $nuevas = [
            'E' => 'NC', // Notas de Crédito
            'P' => 'CP', // Complementos de Pago
        ];

        $companies = DB::table('companies')->get(['id']);

        foreach ($companies as $company) {
            foreach ($nuevas as $tipo => $serie) {
                $yaExiste = DB::table('invoice_series')
                    ->where('company_id', $company->id)
                    ->where('serie', $serie)
                    ->where('tipo_comprobante', $tipo)
                    ->exists();

                if ($yaExiste) {
                    continue;
                }

                // Desmarcar la serie "A" de este tipo como default (se conserva)
                DB::table('invoice_series')
                    ->where('company_id', $company->id)
                    ->where('tipo_comprobante', $tipo)
                    ->update(['es_default' => false]);

                DB::table('invoice_series')->insert([
                    'company_id'       => $company->id,
                    'serie'            => $serie,
                    'tipo_comprobante' => $tipo,
                    'folio_actual'     => 0,
                    'folio_inicio'     => 1,
                    'activa'           => true,
                    'es_default'       => true,
                    'descripcion'      => 'Serie por defecto (creada por migración) — separada de la serie A de facturas',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('invoice_series')
            ->whereIn('serie', ['NC', 'CP'])
            ->where('descripcion', 'like', 'Serie por defecto (creada por migración)%')
            ->delete();
    }
};
