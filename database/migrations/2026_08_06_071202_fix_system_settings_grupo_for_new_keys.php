<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SystemSetting::set() no fijaba 'grupo' al crear una fila nueva, así que
 * cualquier ajuste guardado por primera vez (antes de este fix) cayó en el
 * default de la tabla ('general') en vez de su grupo real. Como las pantallas
 * filtran por grupo, esas filas quedaron invisibles en su sección aunque sí
 * se habían guardado. Esta migración las reubica en el grupo correcto.
 */
return new class extends Migration
{
    public function up(): void
    {
        $gruposPorClave = [
            'whatsapp.base_url'                                    => 'whatsapp',
            'whatsapp.instance'                                    => 'whatsapp',
            'whatsapp.api_key'                                     => 'whatsapp',
            'logistica.permitir_completar_traspaso_directo'        => 'logistica',
        ];

        foreach ($gruposPorClave as $clave => $grupo) {
            DB::table('system_settings')
                ->where('clave', $clave)
                ->update(['grupo' => $grupo]);
        }
    }

    public function down(): void
    {
        // No revertimos: reubicar de vuelta a 'general' no aporta nada.
    }
};
