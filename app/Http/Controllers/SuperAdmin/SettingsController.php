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

        return view('superadmin.settings.index', compact('general', 'facturacion', 'correo', 'auth', 'logistica', 'whatsapp'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app.nombre'                 => ['nullable', 'string', 'max:100'],
            'app.timezone'               => ['nullable', 'string', 'max:50'],
            'app.logo'                   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'facturacion.version_cfdi'   => ['nullable', 'string', 'max:5'],
            'facturacion.exportacion'    => ['nullable', 'string', 'max:2'],
            'facturacion.alerta_timbres' => ['nullable', 'integer', 'min:1'],
            'correo.from_name'           => ['nullable', 'string', 'max:100'],
            'correo.from_address'        => ['nullable', 'email', 'max:150'],
            'auth_login_mode'            => ['nullable', 'string', 'in:email,username'],
            'auth_username_domain'       => ['nullable', 'string', 'max:100'],
            'whatsapp.base_url'          => ['nullable', 'string', 'max:255'],
            'whatsapp.instance'          => ['nullable', 'string', 'max:100'],
            'whatsapp.api_key'           => ['nullable', 'string', 'max:255'],
        ]);

        // Grupo real de cada clave — SystemSetting::set() no lo infiere solo, y sin
        // esto una fila nueva cae en el grupo 'general' por default de la tabla,
        // quedando invisible en su sección aunque sí se guardó.
        $gruposPorClave = [
            'app.nombre'                 => 'general',
            'app.timezone'               => 'general',
            'facturacion.version_cfdi'   => 'facturacion',
            'facturacion.exportacion'    => 'facturacion',
            'facturacion.alerta_timbres' => 'facturacion',
            'correo.from_name'           => 'correo',
            'correo.from_address'        => 'correo',
            'whatsapp.base_url'          => 'whatsapp',
            'whatsapp.instance'          => 'whatsapp',
        ];

        foreach ($data as $clave => $valor) {
            if ($clave === 'whatsapp.api_key') continue; // se maneja aparte para no borrarla si se deja en blanco
            if ($valor !== null && !($valor instanceof \Illuminate\Http\UploadedFile)) {
                SystemSetting::set($clave, $valor, is_int($valor) ? 'integer' : 'string', $gruposPorClave[$clave] ?? null);
            }
        }

        // El campo de API Key es tipo password: si llega vacío, se conserva la que ya estaba guardada.
        if (filled($data['whatsapp.api_key'] ?? null)) {
            SystemSetting::set('whatsapp.api_key', $data['whatsapp.api_key'], 'string', 'whatsapp');
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

        if ($request->hasFile('app.logo')) {
            $path = $request->file('app.logo')->store('logos', 'public');
            SystemSetting::set('app.logo_path', $path, 'file', 'general');
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
