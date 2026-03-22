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

        return view('superadmin.settings.index', compact('general', 'facturacion', 'correo'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app.nombre'                 => ['nullable', 'string', 'max:100'],
            'app.timezone'               => ['nullable', 'string', 'max:50'],
            'facturacion.version_cfdi'   => ['nullable', 'string', 'max:5'],
            'facturacion.exportacion'    => ['nullable', 'string', 'max:2'],
            'facturacion.alerta_timbres' => ['nullable', 'integer', 'min:1'],
            'correo.from_name'           => ['nullable', 'string', 'max:100'],
            'correo.from_address'        => ['nullable', 'email', 'max:150'],
        ]);

        foreach ($data as $clave => $valor) {
            if ($valor !== null) {
                SystemSetting::set($clave, $valor, is_int($valor) ? 'integer' : 'string');
            }
        }

        if ($request->hasFile('app.logo')) {
            $path = $request->file('app.logo')->store('logos', 'public');
            SystemSetting::set('app.logo_path', $path, 'file');
        }

        return back()->with('success', 'Configuración guardada correctamente.');
    }
}
