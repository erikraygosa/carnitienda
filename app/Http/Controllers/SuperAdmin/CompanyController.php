<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\StampCounter;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::with(['fiscalData', 'csdActivo'])
            ->withCount('users')
            ->latest()
            ->get()
            ->map(function ($company) {
                $counter = StampCounter::activoParaEmpresa($company->id);
                $company->stamp_counter      = $counter;
                $company->timbres_restantes  = $counter?->timbresRestantes() ?? 0;
                $company->alerta_timbres     = $counter?->alertaRestantes();
                return $company;
            });

        return view('superadmin.companies.index', compact('companies'));
    }

    public function toggle(Company $company)
    {
        $company->update(['activo' => ! $company->activo]);
        $estado = $company->activo ? 'activada' : 'desactivada';
        return back()->with('success', "Empresa {$estado} correctamente.");
    }

    public function addTimbres(Request $request, Company $company)
    {
        $data = $request->validate([
            'cantidad'        => ['required', 'integer', 'min:1', 'max:10000'],
            'vigencia_inicio' => ['required', 'date'],
            'vigencia_fin'    => ['required', 'date', 'after:vigencia_inicio'],
            'notas'           => ['nullable', 'string', 'max:300'],
        ]);

        StampCounter::where('company_id', $company->id)->where('activo', true)->update(['activo' => false]);

        StampCounter::create([
            'company_id'          => $company->id,
            'timbres_contratados' => $data['cantidad'],
            'timbres_usados'      => 0,
            'timbres_cancelados'  => 0,
            'vigencia_inicio'     => $data['vigencia_inicio'],
            'vigencia_fin'        => $data['vigencia_fin'],
            'activo'              => true,
            'notas'               => $data['notas'] ?? null,
        ]);

        return back()->with('success', "{$data['cantidad']} timbres agregados a {$company->nombre_display}.");
    }

    public function consumo(Request $request, Company $company)
    {
        $desde = $request->input('desde', now()->subDays(29)->format('Y-m-d'));
        $hasta = $request->input('hasta', now()->format('Y-m-d'));

        // Agrupar por día si el rango es corto, por mes si es largo (evita cientos de barras)
        $dias      = \Carbon\Carbon::parse($desde)->diffInDays(\Carbon\Carbon::parse($hasta)) + 1;
        $porMes    = $dias > 92;
        $formatoSql = $porMes ? '%Y-%m' : '%Y-%m-%d';

        $filas = Invoice::where('company_id', $company->id)
            ->whereIn('estatus', ['TIMBRADA', 'CANCELADA'])
            ->whereBetween('fecha', [$desde, $hasta . ' 23:59:59'])
            ->selectRaw("DATE_FORMAT(fecha, '{$formatoSql}') as periodo, estatus, COUNT(*) as total")
            ->groupBy('periodo', 'estatus')
            ->orderBy('periodo')
            ->get();

        $periodos = $filas->pluck('periodo')->unique()->sort()->values();

        $timbradas  = $periodos->map(fn ($p) => (int) $filas->firstWhere(fn ($f) => $f->periodo === $p && $f->estatus === 'TIMBRADA')?->total ?? 0);
        $canceladas = $periodos->map(fn ($p) => (int) $filas->firstWhere(fn ($f) => $f->periodo === $p && $f->estatus === 'CANCELADA')?->total ?? 0);

        $totalTimbradas  = $timbradas->sum();
        $totalCanceladas = $canceladas->sum();

        $counter = StampCounter::activoParaEmpresa($company->id);

        return view('superadmin.companies.consumo', [
            'company'         => $company,
            'desde'           => $desde,
            'hasta'           => $hasta,
            'porMes'          => $porMes,
            'periodos'        => $periodos,
            'timbradas'       => $timbradas,
            'canceladas'      => $canceladas,
            'totalTimbradas'  => $totalTimbradas,
            'totalCanceladas' => $totalCanceladas,
            'counter'         => $counter,
        ]);
    }
}
