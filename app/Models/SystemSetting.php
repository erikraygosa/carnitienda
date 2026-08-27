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
    public static function set(string $clave, mixed $valor, string $tipo = 'string', ?string $grupo = null): void
    {
        $valorStr = is_array($valor) ? json_encode($valor) : (string) $valor;

        $attrs = ['valor' => $valorStr, 'tipo' => $tipo];
        // Solo tocamos 'grupo' si nos lo pasan explícitamente — si no, se respeta
        // el que ya tenía la fila (o el default de la tabla si es una fila nueva).
        if ($grupo !== null) {
            $attrs['grupo'] = $grupo;
        }

        static::updateOrCreate(['clave' => $clave], $attrs);

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
            ['clave' => 'tickets.mostrar_logo', 'valor' => '1',                'tipo' => 'boolean', 'grupo' => 'general',      'descripcion' => 'Imprime el logo en los tickets térmicos (POS, Pedidos, Corte de caja)'],
            ['clave' => 'app.timezone',         'valor' => 'America/Mexico_City', 'tipo' => 'string', 'grupo' => 'general',   'descripcion' => 'Zona horaria del sistema'],

            // Facturación
            ['clave' => 'facturacion.version_cfdi',   'valor' => '4.0',  'tipo' => 'string',  'grupo' => 'facturacion', 'descripcion' => 'Versión CFDI'],
            ['clave' => 'facturacion.exportacion',     'valor' => '01',   'tipo' => 'string',  'grupo' => 'facturacion', 'descripcion' => 'Clave exportación SAT'],
            ['clave' => 'facturacion.alerta_timbres',  'valor' => '50',   'tipo' => 'integer', 'grupo' => 'facturacion', 'descripcion' => 'Alerta cuando queden N timbres'],

            // Correo
            ['clave' => 'correo.from_name',    'valor' => config('app.name'), 'tipo' => 'string', 'grupo' => 'correo', 'descripcion' => 'Nombre del remitente'],
            ['clave' => 'correo.from_address', 'valor' => null, 'tipo' => 'string', 'grupo' => 'correo', 'descripcion' => 'Email del remitente'],

            // Autenticación
            ['clave' => 'auth.login_mode',        'valor' => 'email', 'tipo' => 'string', 'grupo' => 'auth', 'descripcion' => 'Modo de inicio de sesión: email o username'],
            ['clave' => 'auth.username_domain',    'valor' => '',      'tipo' => 'string', 'grupo' => 'auth', 'descripcion' => 'Dominio que se agrega al nombre de usuario al iniciar sesión'],

            // Logística
            ['clave' => 'logistica.permitir_completar_traspaso_directo', 'valor' => '0', 'tipo' => 'boolean', 'grupo' => 'logistica', 'descripcion' => 'Permite completar un traspaso sin pasar por un despacho (rompe el flujo normal PENDIENTE → ASIGNADO → EN_RUTA)'],

            // Pedidos
            ['clave' => 'pedidos.mostrar_iva', 'valor' => '1', 'tipo' => 'boolean', 'grupo' => 'pedidos', 'descripcion' => 'Muestra la columna de % IVA en la creación/edición de pedidos'],

            // Reportes
            ['clave' => 'reportes.liquidaciones_pendientes_modo', 'valor' => 'procesar', 'tipo' => 'string', 'grupo' => 'reportes', 'descripcion' => "Qué pedidos cuenta el widget 'pendientes' de Reportes → Liquidaciones: 'procesar' (APROBADO/PREPARANDO, aún no pasan por Procesar) o 'surtir' (PROCESADO, ya en Salida de producto pero sin terminar de despachar)"],

            // Etiquetas de surtido (impresora ZPL en el panel de surtido)
            ['clave' => 'etiquetas.modo_impresion',    'valor' => 'ticket', 'tipo' => 'string',  'grupo' => 'etiquetas', 'descripcion' => "Qué imprime el Panel de Surtido: 'ticket' (comportamiento actual, vía navegador) o 'zpl' (etiquetas ZPL enviadas directo a una impresora térmica de etiquetas)"],
            ['clave' => 'etiquetas.imprimir_por_cajas', 'valor' => '0',      'tipo' => 'boolean', 'grupo' => 'etiquetas', 'descripcion' => 'Con modo ZPL activo: imprime una etiqueta por cada caja (pide el peso de cada una) en vez de una sola etiqueta resumen por línea'],
            ['clave' => 'etiquetas.impresora_ip',       'valor' => '',       'tipo' => 'string',  'grupo' => 'etiquetas', 'descripcion' => 'IP en la red local de la impresora de etiquetas ZPL'],
            ['clave' => 'etiquetas.impresora_puerto',   'valor' => '9100',   'tipo' => 'integer', 'grupo' => 'etiquetas', 'descripcion' => 'Puerto TCP de la impresora de etiquetas (9100 = estándar RAW en la mayoría de impresoras ZPL)'],

            // WhatsApp (Evolution API)
            ['clave' => 'whatsapp.base_url', 'valor' => env('EVO_API_BASE_URL', ''), 'tipo' => 'string', 'grupo' => 'whatsapp', 'descripcion' => 'URL base del servidor Evolution API, ej. https://evo.midominio.com'],
            ['clave' => 'whatsapp.instance', 'valor' => env('EVO_API_INSTANCE', ''), 'tipo' => 'string', 'grupo' => 'whatsapp', 'descripcion' => 'Nombre de la instancia de WhatsApp en Evolution API'],
            ['clave' => 'whatsapp.api_key',  'valor' => env('EVO_API_KEY', ''),      'tipo' => 'string', 'grupo' => 'whatsapp', 'descripcion' => 'API Key de la instancia'],

            // Chat de asistencia (proveedor de IA tipo OpenAI)
            ['clave' => 'openai.api_key',  'valor' => env('OPENAI_API_KEY', ''),                      'tipo' => 'string', 'grupo' => 'asistente', 'descripcion' => 'API key del proveedor de IA usado por el chat de asistencia'],
            ['clave' => 'openai.model',    'valor' => env('OPENAI_MODEL', 'gpt-4o-mini'),              'tipo' => 'string', 'grupo' => 'asistente', 'descripcion' => 'Modelo usado por el chat de asistencia'],
            ['clave' => 'openai.base_url', 'valor' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), 'tipo' => 'string', 'grupo' => 'asistente', 'descripcion' => 'URL base de la API del proveedor de IA'],

            // Almacén que usa el chat de asistencia al crear un pedido en borrador,
            // si no se define, cae al almacén marcado is_primary o al primero activo.
            ['clave' => 'pedidos.asistente_almacen_id', 'valor' => null, 'tipo' => 'integer', 'grupo' => 'asistente', 'descripcion' => 'Almacén por defecto para pedidos creados desde el chat de asistencia'],
        ];

        foreach ($defaults as $setting) {
            static::firstOrCreate(
                ['clave' => $setting['clave']],
                $setting
            );
        }
    }
}