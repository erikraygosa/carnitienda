<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PacConfiguration;
use App\Models\StampCounter;
use App\Models\SystemSetting;

class DashboardController extends Controller
{
    public function index()
    {
        $pacActivo       = PacConfiguration::activo()->first();
        $totalEmpresas   = Company::count();
        $empresasActivas = Company::where('activo', true)->count();

        $timbresHoy = Invoice::where('estatus', 'TIMBRADA')
            ->whereDate('updated_at', today())
            ->count();

        $timbresTotal = Invoice::where('estatus', 'TIMBRADA')->count();

        $contadores = StampCounter::with('company')
            ->activo()
            ->vigente()
            ->get();

        $alertasTimbres = $contadores->filter(
            fn($c) => in_array($c->alertaRestantes(), ['critico', 'agotado'])
        );

        $empresasConTimbres = $contadores->map(fn($c) => [
            'empresa'    => $c->company?->nombre_display,
            'restantes'  => $c->timbresRestantes(),
            'usados'     => $c->timbres_usados,
            'contratados'=> $c->timbres_contratados,
            'porcentaje' => $c->porcentajeUsado(),
            'alerta'     => $c->alertaRestantes(),
        ]);

        return view('superadmin.dashboard', compact(
            'pacActivo',
            'totalEmpresas',
            'empresasActivas',
            'timbresHoy',
            'timbresTotal',
            'alertasTimbres',
            'empresasConTimbres'
        ));
    }
}