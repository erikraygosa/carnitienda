<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 'ticket' conserva el comportamiento actual (impresión HTML vía
        // navegador) — no rompe nada en instalaciones existentes hasta que
        // alguien active 'zpl' explícitamente en SuperAdmin → Configuración.
        $defaults = [
            [
                'clave'       => 'etiquetas.modo_impresion',
                'valor'       => 'ticket',
                'tipo'        => 'string',
                'grupo'       => 'etiquetas',
                'descripcion' => "Qué imprime el Panel de Surtido: 'ticket' (comportamiento actual, vía navegador) o 'zpl' (etiquetas ZPL enviadas directo a una impresora térmica de etiquetas)",
            ],
            [
                'clave'       => 'etiquetas.imprimir_por_cajas',
                'valor'       => '0',
                'tipo'        => 'boolean',
                'grupo'       => 'etiquetas',
                'descripcion' => 'Con modo ZPL activo: imprime una etiqueta por cada caja (pide el peso de cada una) en vez de una sola etiqueta resumen por línea',
            ],
            [
                'clave'       => 'etiquetas.impresora_ip',
                'valor'       => '',
                'tipo'        => 'string',
                'grupo'       => 'etiquetas',
                'descripcion' => 'IP en la red local de la impresora de etiquetas ZPL',
            ],
            [
                'clave'       => 'etiquetas.impresora_puerto',
                'valor'       => '9100',
                'tipo'        => 'integer',
                'grupo'       => 'etiquetas',
                'descripcion' => 'Puerto TCP de la impresora de etiquetas (9100 = estándar RAW en la mayoría de impresoras ZPL)',
            ],
        ];

        foreach ($defaults as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['clave' => $setting['clave']],
                array_merge($setting, [
                    'es_publica' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('clave', [
            'etiquetas.modo_impresion',
            'etiquetas.imprimir_por_cajas',
            'etiquetas.impresora_ip',
            'etiquetas.impresora_puerto',
        ])->delete();
    }
};
