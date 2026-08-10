<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Antes 'is_primary' no era editable desde la UI, así que en varias
        // instalaciones puede no estar seteado (o estar mal) aunque el
        // almacén se llame/código "MATRIZ". Lo dejamos consistente: si hay
        // un almacén con código o nombre "MATRIZ" (o similar), ese es el
        // principal; si no, se conserva el que ya estuviera marcado; si
        // ninguno, el de menor id.
        $matrizId = DB::table('warehouses')
            ->where(function ($q) {
                $q->where('codigo', 'like', 'MATRIZ%')->orWhere('nombre', 'like', 'MATRIZ%');
            })
            ->orderBy('id')
            ->value('id');

        if (! $matrizId) {
            $matrizId = DB::table('warehouses')->where('is_primary', 1)->value('id')
                ?? DB::table('warehouses')->orderBy('id')->value('id');
        }

        if ($matrizId) {
            DB::table('warehouses')->update(['is_primary' => false]);
            DB::table('warehouses')->where('id', $matrizId)->update(['is_primary' => true]);
        }
    }

    public function down(): void
    {
        // No reversible de forma segura (no sabemos el estado previo real).
    }
};
