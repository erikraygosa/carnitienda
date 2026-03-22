<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'clave',
        'valor',
        'tipo',
        'grupo',
        'descripcion',
        'es_publica',
    ];

    protected $casts = [
        'es_publica' => 'boolean',
    ];

    // ------------------------------------------------------------------
    // Acceso estático con cache
    // ------------------------------------------------------------------

    /**
     * Obtiene un setting por clave con cache de 60 minutos
     */
    public static function get(string $clave, mixed $default = null): mixed
    {
        return Cache::remember("setting_{$clave}", 3600, function () use ($clave, $default) {
            $setting = static::where('clave', $clave)->first();
            if (! $setting) return $default;

            return match($setting->tipo) {
                'boolean' => (bool) $setting->valor,
                'integer' => (int) $setting->valor,
                'json'    => json_decode($setting->valor, true),
                default   => $setting->valor,
            };
        });
    }

    /**
     * Guarda o actualiza un setting y limpia el cache
     */
    public static function set(string $clave, mixed $valor, string $tipo = 'string'): void
    {
        $valorStr = is_array($valor) ? json_encode($valor) : (string) $valor;

        static::updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valorStr, 'tipo' => $tipo]
        );

        Cache::forget("setting_{$clave}");
    }

    /**
     * Obtiene todos los settings de un grupo
     */
    public static function grupo(string $grupo): array
    {
        return static::where('grupo', $grupo)
            ->get()
            ->pluck('valor', 'clave')
            ->toArray();
    }

    // ------------------------------------------------------------------
    // Settings predeterminados del sistema
    // ------------------------------------------------------------------
    public static function seed(): void
    {
        $defaults = [
            // General
            ['clave' => 'app.nombre',          'valor' => config('app.name'), 'tipo' => 'string',  'grupo' => 'general',      'descripcion' => 'Nombre del sistema'],
            ['clave' => 'app.logo_path',        'valor' => null,              'tipo' => 'file',    'grupo' => 'general',      'descripcion' => 'Ruta del logo principal'],
            ['clave' => 'app.timezone',         'valor' => 'America/Mexico_City', 'tipo' => 'string', 'grupo' => 'general',   'descripcion' => 'Zona horaria del sistema'],

            // Facturación
            ['clave' => 'facturacion.version_cfdi',   'valor' => '4.0',  'tipo' => 'string',  'grupo' => 'facturacion', 'descripcion' => 'Versión CFDI'],
            ['clave' => 'facturacion.exportacion',     'valor' => '01',   'tipo' => 'string',  'grupo' => 'facturacion', 'descripcion' => 'Clave exportación SAT'],
            ['clave' => 'facturacion.alerta_timbres',  'valor' => '50',   'tipo' => 'integer', 'grupo' => 'facturacion', 'descripcion' => 'Alerta cuando queden N timbres'],

            // Correo
            ['clave' => 'correo.from_name',    'valor' => config('app.name'), 'tipo' => 'string', 'grupo' => 'correo', 'descripcion' => 'Nombre del remitente'],
            ['clave' => 'correo.from_address', 'valor' => null, 'tipo' => 'string', 'grupo' => 'correo', 'descripcion' => 'Email del remitente'],
        ];

        foreach ($defaults as $setting) {
            static::firstOrCreate(
                ['clave' => $setting['clave']],
                $setting
            );
        }
    }
}