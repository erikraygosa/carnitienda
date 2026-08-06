<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
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

        return view('superadmin.settings.index', compact('general', 'facturacion', 'correo', 'auth', 'logistica'));
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
        ]);

        foreach ($data as $clave => $valor) {
            if ($valor !== null && !($valor instanceof \Illuminate\Http\UploadedFile)) {
                SystemSetting::set($clave, $valor, is_int($valor) ? 'integer' : 'string');
            }
        }

        // Campos de autenticación usan guión bajo en el formulario pero se almacenan con punto
        SystemSetting::set('auth.login_mode',       $request->input('auth_login_mode', 'email'), 'string');
        SystemSetting::set('auth.username_domain',  $request->input('auth_username_domain', ''), 'string');

        // Checkbox: si no viene en el request es porque está desmarcado.
        SystemSetting::set(
            'logistica.permitir_completar_traspaso_directo',
            $request->boolean('logistica_permitir_completar_traspaso_directo') ? '1' : '0',
            'boolean'
        );

        if ($request->hasFile('app.logo')) {
            $path = $request->file('app.logo')->store('logos', 'public');
            SystemSetting::set('app.logo_path', $path, 'file');
        }

        return back()->with('success', 'Configuración guardada correctamente.');
    }
}
