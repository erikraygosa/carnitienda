<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\WhatsappSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function index()
    {
        $general     = SystemSetting::where('grupo', 'general')->get()->keyBy('clave');
        $facturacion = SystemSetting::where('grupo', 'facturacion')->get()->keyBy('clave');
        $correo      = SystemSetting::where('grupo', 'correo')->get()->keyBy('clave');
        $auth        = SystemSetting::where('grupo', 'auth')->get()->keyBy('clave');
        $logistica   = SystemSetting::where('grupo', 'logistica')->get()->keyBy('clave');
        $whatsapp    = SystemSetting::where('grupo', 'whatsapp')->get()->keyBy('clave');
        $precios     = SystemSetting::where('grupo', 'precios')->get()->keyBy('clave');
        $pedidos     = SystemSetting::where('grupo', 'pedidos')->get()->keyBy('clave');
        $reportes    = SystemSetting::where('grupo', 'reportes')->get()->keyBy('clave');

        return view('superadmin.settings.index', compact('general', 'facturacion', 'correo', 'auth', 'logistica', 'whatsapp', 'precios', 'pedidos', 'reportes'));
    }

    public function update(Request $request)
    {
        // IMPORTANTE: los <input name="..."> de este formulario NO pueden
        // llevar puntos literales (ej. "app.nombre"). PHP convierte
        // automáticamente los puntos (y espacios) a guión bajo en las claves
        // de $_POST/$_FILES al parsear la petición — es un comportamiento
        // del propio PHP, no de Laravel. Un campo name="app.logo" llega al
        // servidor como $_FILES['app_logo'], nunca como $_FILES['app.logo'].
        // Antes este formulario usaba puntos en varios campos y por eso
        // nunca se guardaban (incluido el logo) sin ningún error visible.
        // Por eso aquí los nombres de campo van con guión bajo, y se
        // traducen a la clave real (con punto) de system_settings al guardar.
        $data = $request->validate([
            'app_nombre'                 => ['nullable', 'string', 'max:100'],
            'app_timezone'               => ['nullable', 'string', 'max:50'],
            'app_logo'                   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'facturacion_version_cfdi'   => ['nullable', 'string', 'max:5'],
            'facturacion_exportacion'    => ['nullable', 'string', 'max:2'],
            'facturacion_alerta_timbres' => ['nullable', 'integer', 'min:1'],
            'correo_from_name'           => ['nullable', 'string', 'max:100'],
            'correo_from_address'        => ['nullable', 'email', 'max:150'],
            'auth_login_mode'            => ['nullable', 'string', 'in:email,username'],
            'auth_username_domain'       => ['nullable', 'string', 'max:100'],
            'whatsapp_base_url'          => ['nullable', 'string', 'max:255'],
            'whatsapp_instance'          => ['nullable', 'string', 'max:100'],
            'whatsapp_api_key'           => ['nullable', 'string', 'max:255'],
            'precios_modo'               => ['nullable', 'string', 'in:global,almacen'],
        ]);

        // Nombre de campo (guión bajo) → [clave real con punto, grupo]
        $mapa = [
            'app_nombre'                 => ['app.nombre', 'general'],
            'app_timezone'               => ['app.timezone', 'general'],
            'facturacion_version_cfdi'   => ['facturacion.version_cfdi', 'facturacion'],
            'facturacion_exportacion'    => ['facturacion.exportacion', 'facturacion'],
            'facturacion_alerta_timbres' => ['facturacion.alerta_timbres', 'facturacion'],
            'correo_from_name'           => ['correo.from_name', 'correo'],
            'correo_from_address'        => ['correo.from_address', 'correo'],
            'whatsapp_base_url'          => ['whatsapp.base_url', 'whatsapp'],
            'whatsapp_instance'          => ['whatsapp.instance', 'whatsapp'],
        ];

        foreach ($mapa as $campo => [$clave, $grupo]) {
            $valor = $data[$campo] ?? null;
            if ($valor !== null) {
                SystemSetting::set($clave, $valor, is_int($valor) ? 'integer' : 'string', $grupo);
            }
        }

        // El campo de API Key es tipo password: si llega vacío, se conserva la que ya estaba guardada.
        if (filled($data['whatsapp_api_key'] ?? null)) {
            SystemSetting::set('whatsapp.api_key', $data['whatsapp_api_key'], 'string', 'whatsapp');
        }

        // Campos de autenticación usan guión bajo en el formulario pero se almacenan con punto
        SystemSetting::set('auth.login_mode',       $request->input('auth_login_mode', 'email'), 'string', 'auth');
        SystemSetting::set('auth.username_domain',  $request->input('auth_username_domain', ''), 'string', 'auth');

        // Checkbox: si no viene en el request es porque está desmarcado.
        SystemSetting::set(
            'logistica.permitir_completar_traspaso_directo',
            $request->boolean('logistica_permitir_completar_traspaso_directo') ? '1' : '0',
            'boolean',
            'logistica'
        );

        SystemSetting::set(
            'precios.modo',
            $request->input('precios_modo') === 'almacen' ? 'almacen' : 'global',
            'string',
            'precios'
        );

        // Checkbox: si no viene en el request es porque está desmarcado.
        SystemSetting::set(
            'pedidos.mostrar_iva',
            $request->boolean('pedidos_mostrar_iva') ? '1' : '0',
            'boolean',
            'pedidos'
        );

        SystemSetting::set(
            'reportes.liquidaciones_pendientes_modo',
            $request->input('reportes_liquidaciones_pendientes_modo') === 'surtir' ? 'surtir' : 'procesar',
            'string',
            'reportes'
        );

        // Checkbox: si no viene en el request es porque está desmarcado.
        SystemSetting::set(
            'tickets.mostrar_logo',
            $request->boolean('tickets_mostrar_logo') ? '1' : '0',
            'boolean',
            'general'
        );

        // El sidebar/login y los tickets térmicos (POS, Pedidos, Corte de
        // caja) leen directamente el archivo public/logo.jpg — no un campo
        // de base de datos. Por eso el upload reemplaza ese archivo tal
        // cual, en vez de guardarlo en el disco 'public' de Storage (que
        // nadie lee).
        if ($request->hasFile('app_logo')) {
            $request->file('app_logo')->move(public_path(), 'logo.jpg');
            SystemSetting::set('app.logo_path', 'logo.jpg', 'file', 'general');
        }

        return back()->with('success', 'Configuración guardada correctamente.');
    }

    public function testWhatsapp(Request $request, WhatsappSender $whatsapp)
    {
        $request->validate([
            'telefono' => ['required', 'string', 'max:20'],
        ]);

        if (!$whatsapp->isConfigured()) {
            return back()->with('error', 'Faltan datos de WhatsApp por configurar (URL base, instancia o API Key).');
        }

        try {
            $resp = $whatsapp->sendText($request->telefono, 'Mensaje de prueba desde ' . config('app.name') . ' — la conexión de WhatsApp funciona correctamente.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error de conexión: ' . $e->getMessage());
        }

        if ($resp['ok'] ?? false) {
            return back()->with('success', '✓ Mensaje de prueba enviado correctamente.');
        }

        return back()->with('error', 'WhatsApp respondió ' . ($resp['status'] ?? '?') . ': ' . (is_string($resp['body']) ? $resp['body'] : json_encode($resp['body'] ?? [])));
    }
}
