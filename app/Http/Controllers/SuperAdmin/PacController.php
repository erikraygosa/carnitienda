<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PacConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PacController extends Controller
{
    public function index()
    {
        $pacs = PacConfiguration::orderBy('activo', 'desc')->get();
        return view('superadmin.pac.index', compact('pacs'));
    }

    public function update(Request $request, PacConfiguration $pac)
    {
        $data = $request->validate([
            'nombre'     => ['required', 'string', 'max:100'],
            'ambiente'   => ['required', 'in:sandbox,produccion'],
            'habilitado' => ['nullable', 'boolean'],
            'notas'      => ['nullable', 'string', 'max:500'],
            'api_key'    => ['nullable', 'string', 'max:500'],
            'api_secret' => ['nullable', 'string', 'max:500'],
        ]);

        $pac->update([
            'nombre'     => $data['nombre'],
            'ambiente'   => $data['ambiente'],
            'habilitado' => $request->boolean('habilitado'),
            'notas'      => $data['notas'],
        ]);

        if (! empty($data['api_key']))    $pac->setApiKey($data['api_key']);
        if (! empty($data['api_secret'])) $pac->setApiSecret($data['api_secret']);

        return back()->with('success', 'Configuración del PAC actualizada.');
    }

    public function activar(PacConfiguration $pac)
    {
        if (! $pac->habilitado) {
            return back()->with('error', 'Este PAC no está habilitado.');
        }
        $pac->activar();
        return back()->with('success', "PAC '{$pac->nombre}' activado correctamente.");
    }

    public function test(PacConfiguration $pac)
    {
        try {
            $apiKey = $pac->getApiKey();
            if (! $apiKey) return back()->with('error', 'No hay API Key configurada.');

            if ($pac->driver === 'factuapi') {
                $url = 'https://www.facturapi.io/v2/tools/tax_id_validation?tax_id=ABC101010111';
    
                $response = Http::withToken($apiKey)->timeout(10)->get($url);

                if ($response->successful()) {
                    return back()->with('success', "✓ Conexión con {$pac->nombre} exitosa ({$pac->ambiente_label}).");
                }
                return back()->with('error', "Error HTTP {$response->status()}");
            }

            return back()->with('error', 'Test no disponible para este PAC.');
        } catch (\Throwable $e) {
            return back()->with('error', "Error: {$e->getMessage()}");
        }
    }
}
