<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La tabla pac_configurations nunca tuvo un registro base en producción
     * (no hay pantalla para "crear" un PAC, solo para editar uno existente),
     * así que timbrar siempre fallaba: PacConfiguration::activo()->firstOrFail()
     * no encontraba nada. Se inserta el registro de Factuapi en modo sandbox,
     * deshabilitado hasta que se cargue la API key real desde el panel de
     * superadmin (PAC / Timbrado) y se active manualmente.
     */
    public function up(): void
    {
        $exists = DB::table('pac_configurations')->where('driver', 'factuapi')->exists();

        if (! $exists) {
            DB::table('pac_configurations')->insert([
                'driver'       => 'factuapi',
                'nombre'       => 'Factuapi',
                'ambiente'     => 'sandbox',
                'activo'       => false,
                'habilitado'   => false,
                'version_cfdi' => '4.0',
                'notas'        => 'Registro base creado por migración. Cargar API key en el panel de superadmin y activar manualmente.',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('pac_configurations')->where('driver', 'factuapi')->delete();
    }
};
