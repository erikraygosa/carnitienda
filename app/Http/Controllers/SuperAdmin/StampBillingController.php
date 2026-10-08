<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\StampBillingConfig;
use App\Models\StampCut;
use App\Services\StampBillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StampBillingController extends Controller
{
    public function __construct(private StampBillingService $svc) {}

    public function index()
    {
        $filas = Company::with('fiscalData')->orderBy('id')->get()->map(function ($company) {
            $cfg = StampBillingConfig::where('company_id', $company->id)->first();
            $fila = ['company' => $company, 'cfg' => $cfg, 'actual' => null, 'pendientes' => 0];
            if ($cfg && $cfg->activo) {
                [$i, $f] = $this->svc->periodoQueContiene($cfg, now());
                $fila['actual'] = ['inicio' => $i, 'fin' => $f] + $this->svc->calcular($cfg, $i, $f);
                $fila['pendientes'] = StampCut::where('company_id', $company->id)->where('estado', 'pendiente')->count();
            }
            return $fila;
        });

        return view('superadmin.stamps.index', compact('filas'));
    }

    public function company(Company $company)
    {
        $cfg = StampBillingConfig::where('company_id', $company->id)->first();
        $periodos = [];

        if ($cfg) {
            $cortes = StampCut::where('company_id', $company->id)->get()
                ->keyBy(fn ($c) => $c->periodo_inicio->toDateString());
            $hoy = now()->startOfDay();

            foreach ($this->svc->periodos($cfg) as [$i, $f]) {
                $corte = $cortes->get($i->toDateString());
                $periodos[] = [
                    'inicio'   => $i,
                    'fin'      => $f,
                    'abierto'  => $f->gte($hoy),
                    'corte'    => $corte,
                    'calculo'  => $corte ? null : $this->svc->calcular($cfg, $i, $f),
                ];
            }
        }

        return view('superadmin.stamps.company', compact('company', 'cfg', 'periodos'));
    }

    public function saveConfig(Request $request, Company $company)
    {
        $data = $request->validate([
            'cortesia_mensual' => ['required', 'integer', 'min:0', 'max:100000'],
            'precio_timbre'    => ['required', 'numeric', 'min:0', 'max:100000'],
            'mensualidad'      => ['required', 'numeric', 'min:0', 'max:10000000'],
            'dia_corte'        => ['required', 'integer', 'min:1', 'max:28'],
            'inicio_cobro'     => ['required', 'date'],
            'activo'           => ['nullable', 'boolean'],
            'notas'            => ['nullable', 'string', 'max:500'],
        ]);
        $data['activo'] = $request->boolean('activo');

        StampBillingConfig::updateOrCreate(['company_id' => $company->id], $data);

        return back()->with('success', 'Configuración de cobro guardada. Los cortes ya generados no cambian.');
    }

    public function generate(Request $request, Company $company)
    {
        $request->validate(['inicio' => ['required', 'date']]);
        $cfg = StampBillingConfig::where('company_id', $company->id)->firstOrFail();

        [$i, $f] = $this->svc->periodoQueContiene($cfg, Carbon::parse($request->input('inicio')));
        if ($f->gte(now()->startOfDay())) {
            return back()->with('error', 'El periodo sigue abierto; el corte se genera cuando termina.');
        }

        $corte = $this->svc->generarCorte($cfg, $i, $f, auth()->id());

        return redirect()->route('superadmin.stamps.ticket', $corte);
    }

    public function ticket(StampCut $cut)
    {
        return view('superadmin.stamps.ticket', [
            'company' => $cut->company,
            'datos'   => $cut->toArray(),
            'inicio'  => $cut->periodo_inicio,
            'fin'     => $cut->periodo_fin,
            'folio'   => $cut->folio,
            'estado'  => $cut->estado,
            'parcial' => false,
        ]);
    }

    public function ticketParcial(Request $request, Company $company)
    {
        $cfg = StampBillingConfig::where('company_id', $company->id)->firstOrFail();
        [$i, $f] = $this->svc->periodoQueContiene($cfg, Carbon::parse($request->input('inicio', now())));

        return view('superadmin.stamps.ticket', [
            'company' => $company,
            'datos'   => $this->svc->calcular($cfg, $i, $f),
            'inicio'  => $i,
            'fin'     => $f,
            'folio'   => 'PARCIAL',
            'estado'  => null,
            'parcial' => true,
        ]);
    }

    public function pay(Request $request, StampCut $cut)
    {
        if ($cut->estado === 'pagado') {
            $cut->update(['estado' => 'pendiente', 'fecha_pago' => null]);
            return back()->with('success', "Corte {$cut->folio} regresado a pendiente.");
        }

        $cut->update(['estado' => 'pagado', 'fecha_pago' => $request->input('fecha_pago') ?: now()->toDateString()]);

        return back()->with('success', "Corte {$cut->folio} marcado como pagado.");
    }
}
