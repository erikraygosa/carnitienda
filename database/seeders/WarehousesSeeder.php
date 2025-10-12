<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WarehousesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Datos base
        $rows = [
            ['codigo' => 'ALM-01', 'nombre' => 'Almacén Central',  'direccion' => 'Calle 1 #100',  'activo' => 1],
            ['codigo' => 'ALM-02', 'nombre' => 'Almacén Sucursal', 'direccion' => 'Av. 50 #200',   'activo' => 1],
        ];

        // Si existe la columna is_primary, la incluimos en el upsert
        $hasPrimary = Schema::hasColumn('warehouses', 'is_primary');

        // Upsert por 'codigo' para no duplicar al re-ejecutar
        foreach ($rows as $i => $r) {
            $payload = array_merge($r, ['updated_at' => $now]);
            if ($hasPrimary) {
                // Central = principal; el resto no
                $payload['is_primary'] = ($r['codigo'] === 'ALM-01') ? 1 : 0;
            }

            DB::table('warehouses')->updateOrInsert(
                ['codigo' => $r['codigo']],
                array_merge($payload, ['created_at' => DB::raw('COALESCE(created_at, NOW())')])
            );
        }

        // Garantizar que haya exactamente uno marcado como principal
        if ($hasPrimary) {
            // Si por alguna razón ninguno quedó como principal, marcar el primero ACTIVO
            $anyPrimary = DB::table('warehouses')->where('is_primary', 1)->exists();
            if (! $anyPrimary) {
                $firstActiveId = DB::table('warehouses')->where('activo', 1)->orderBy('id')->value('id')
                    ?: DB::table('warehouses')->orderBy('id')->value('id');

                if ($firstActiveId) {
                    // Primero quitar el flag a todos y luego poner uno
                    DB::table('warehouses')->update(['is_primary' => 0]);
                    DB::table('warehouses')->where('id', $firstActiveId)->update(['is_primary' => 1]);
                }
            } else {
                // Normalizar: dejar solo uno con is_primary=1 (el de menor id si hubiera más de uno)
                $primaries = DB::table('warehouses')->where('is_primary', 1)->orderBy('id')->pluck('id')->all();
                if (count($primaries) > 1) {
                    $keep = array_shift($primaries);
                    DB::table('warehouses')->whereIn('id', $primaries)->update(['is_primary' => 0]);
                    // asegúrate de que el que conservas siga en 1 (por si hubo carrera)
                    DB::table('warehouses')->where('id', $keep)->update(['is_primary' => 1]);
                }
            }
        }
    }
}
