<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Warehouse;
use App\Services\CashService;
use App\Services\DocumentLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CashRegisterController extends Controller implements HasMiddleware
{
    public function __construct(
        private CashService $cash,
        private DocumentLogService $log,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver cajas', only: ['index', 'show', 'ticket', 'ticketPdf']),
            new Middleware('can:abrir cajas', only: ['create', 'store']),
            new Middleware('can:cerrar cajas', only: ['close']),
        ];
    }

    /**
     * El rol cajero solo puede ver/tocar sus propias cajas (abiertas y
     * cerradas) — no las de otros usuarios ni de otros almacenes. Quien
     * tenga un rol adicional con más alcance (ej. admin) sigue viendo todo.
     */
    private function soloPropias(): bool
    {
        return auth()->user()->hasRole('cajero') && !auth()->user()->hasRole('admin');
    }

    /**
     * 403 si un cajero intenta entrar por URL directa a una caja que no es
     * suya (index() ya la oculta de la lista, pero eso no bloquea /show/N).
     */
    private function authorizeOwnRegister(CashRegister $cash): void
    {
        abort_if($this->soloPropias() && $cash->user_id !== auth()->id(), 403, 'No tienes acceso a esta caja.');
    }

    public function index()
    {
        $search = request('search');

        $soloPropias = $this->soloPropias();

        $registers = CashRegister::with(['warehouse:id,nombre', 'user:id,name'])
            ->when($soloPropias, fn($q) => $q->where('user_id', auth()->id()))
            ->when($search, function($q) use ($search) {
                $q->where('fecha', 'like', "%{$search}%")
                  ->orWhereHas('warehouse', fn($q) => $q->where('nombre', 'like', "%{$search}%"))
                  ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%"))
                  ->orWhere('estatus', 'like', "%{$search}%");
            })
            ->orderByDesc('fecha')
            ->paginate(15)
            ->withQueryString();

        return view('admin.cash.index', compact('registers'));
    }

    public function create()
    {
        $warehouses = Warehouse::orderBy('nombre')->get();
        return view('admin.cash.create', compact('warehouses'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'fecha'        => 'required|date',
            'monto'        => 'nullable|numeric|min:0',
            'notas'        => 'nullable|string',
        ]);

        $reg = $this->cash->open(
            $data['warehouse_id'],
            auth()->id(),
            $data['fecha'],
            $data['monto'] ?? 0,
            $data['notas'] ?? null
        );

        $this->log->log($reg, 'CREADO', null, 'ABIERTO');
        session()->flash('swal', ['icon' => 'success', 'title' => 'Caja abierta', 'text' => 'La caja quedó abierta.']);
        return redirect()->route('admin.cash.show', $reg);
    }

    public function show(CashRegister $cash)
    {
        $this->authorizeOwnRegister($cash);
        $cash->load('movements', 'posSales.client');
        return view('admin.cash.show', ['register' => $cash]);
    }

    public function close(Request $r, CashRegister $cash)
    {
        $this->authorizeOwnRegister($cash);
        $this->cash->close($cash);
        $this->log->log($cash, 'CAMBIO_ESTADO', 'ABIERTO', 'CERRADO');
        session()->flash('swal', ['icon' => 'success', 'title' => 'Caja cerrada', 'text' => 'Se cerró correctamente.']);
        return redirect()->route('admin.cash.index');
    }

    public function ticket(Request $request, CashRegister $cash)
    {
        $this->authorizeOwnRegister($cash);
        $cash->load(['user:id,name', 'warehouse:id,nombre', 'movements', 'posSales.items.product']);
        $company = Company::first();
        $resumen = $request->boolean('resumen');
        return view('admin.cash.ticket', ['register' => $cash, 'company' => $company, 'resumen' => $resumen]);
    }

    public function ticketPdf(Request $request, CashRegister $cash)
    {
        $this->authorizeOwnRegister($cash);
        $cash->load(['movements', 'user', 'warehouse', 'posSales.items.product']);
        $company  = Company::first();
        $register = $cash;
        $resumen  = $request->boolean('resumen');

        $pdf = Pdf::loadView('admin.cash.ticket-pdf', compact('register', 'company', 'resumen'))
            ->setPaper([0, 0, 226.77, 1200], 'portrait');

        return $pdf->stream("caja-{$cash->id}.pdf");
    }
}