<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            [
                'clave'       => 'auth.login_mode',
                'valor'       => 'email',
                'tipo'        => 'string',
                'grupo'       => 'auth',
                'descripcion' => 'Modo de inicio de sesión: email o username',
                'es_publica'  => false,
            ],
            [
                'clave'       => 'auth.username_domain',
                'valor'       => '',
                'tipo'        => 'string',
                'grupo'       => 'auth',
                'descripcion' => 'Dominio que se agrega al nombre de usuario al iniciar sesión',
                'es_publica'  => false,
            ],
        ];

        foreach ($defaults as $row) {
            DB::table('system_settings')->updateOrInsert(
                ['clave' => $row['clave']],
                array_merge($row, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->whereIn('clave', ['auth.login_mode', 'auth.username_domain'])
            ->delete();
    }
};
