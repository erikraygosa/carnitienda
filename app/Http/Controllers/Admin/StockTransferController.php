<?php
// app/Http/Controllers/Admin/StockTransferController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\DocumentLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockTransferController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentLogService $log) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:gestionar traspasos'),
        ];
    }

    public function index()
{
    $warehouses = \App\Models\Warehouse::orderBy('nombre')->get(['id','nombre']);

    $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'creator'])
        ->when(request('search'), fn($q) =>
            $q->where('folio', 'like', '%'.request('search').'%')
        )
        ->when(request('status'), fn($q) =>
            $q->where('status', request('status'))
        )
        ->when(request('from_warehouse'), fn($q) =>
            $q->where('from_warehouse_id', request('from_warehouse'))
        )
        ->when(request('to_warehouse'), fn($q) =>
            $q->where('to_warehouse_id', request('to_warehouse'))
        )
        ->when(request('fecha_desde'), fn($q) =>
            $q->whereDate('fecha', '>=', request('fecha_desde'))
        )
        ->when(request('fecha_hasta'), fn($q) =>
            $q->whereDate('fecha', '<=', request('fecha_hasta'))
        )
        ->latest()
        ->paginate(20)
        ->withQueryString();

    return view('admin.stock.transfers.index', compact('transfers', 'warehouses'));
}

    public function create(Request $request)
    {
        $warehouses = Warehouse::orderBy('nombre')->get(['id', 'nombre']);
        $products   = Product::orderBy('nombre')->get(['id', 'nombre', 'unidad']);

        $prefill = [];
        if ($request->filled('product_id')) {
            $prefill['product_id'] = $request->product_id;
        }
        if ($request->filled('from_warehouse_id')) {
            $prefill['from_warehouse_id'] = $request->from_warehouse_id;
        } else {
            // Por defecto el origen es el almacén principal (MATRIZ) — la
            // mayoría de los traspasos salen de ahí, así se evita tener que
            // seleccionarlo a mano cada vez.
            $primary = Warehouse::where('is_primary', true)->value('id');
            if ($primary) {
                $prefill['from_warehouse_id'] = $primary;
            }
        }

        return view('admin.stock.transfers.create', compact('warehouses', 'products', 'prefill'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id'   => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'fecha'             => ['required', 'date'],
            'notas'             => ['nullable', 'string', 'max:500'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'exists:products,id'],
            'items.*.qty'          => ['required', 'numeric', 'min:0.001'],
            'items.*.presentacion' => ['nullable', 'in:KILOS,PIEZAS,CAJAS'],
            'items.*.comentarios'  => ['nullable', 'string', 'max:200'],
        ]);

        $transfer = DB::transaction(function () use ($data) {
            $transfer = StockTransfer::create([
                'folio'             => StockTransfer::generateFolio(),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id'   => $data['to_warehouse_id'],
                'fecha'             => $data['fecha'],
                'status'            => 'PENDIENTE',
                'notas'             => $data['notas'] ?? null,
                'created_by'        => auth()->id(),
            ]);

            foreach ($data['items'] as $it) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id'        => $it['product_id'],
                    'qty'               => $it['qty'],
                    'presentacion'      => $it['presentacion'] ?? null,
                    'comentarios'       => $it['comentarios'] ?? null,
                ]);
            }

            return $transfer;
        });

        $this->log->log($transfer, 'CREADO', null, 'PENDIENTE');
        return redirect()
            ->route('admin.stock.transfers.show', $transfer)
            ->with('swal', ['icon' => 'success', 'title' => 'Traspaso creado', 'text' => "Folio: {$transfer->folio}"]);
    }

    public function edit(StockTransfer $transfer)
    {
        if ($transfer->status !== 'PENDIENTE') {
            return redirect()->route('admin.stock.transfers.show', $transfer)
                ->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Solo se puede editar mientras el traspaso está PENDIENTE.']);
        }

        $transfer->load('items');
        $warehouses = Warehouse::orderBy('nombre')->get(['id', 'nombre']);
        $products   = Product::orderBy('nombre')->get(['id', 'nombre', 'unidad']);
        $prefill    = [];

        return view('admin.stock.transfers.create', compact('transfer', 'warehouses', 'products', 'prefill'));
    }

    public function update(Request $request, StockTransfer $transfer)
    {
        if ($transfer->status !== 'PENDIENTE') {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Solo se puede editar mientras el traspaso está PENDIENTE.']);
        }

        $data = $request->validate([
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id'   => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'fecha'             => ['required', 'date'],
            'notas'             => ['nullable', 'string', 'max:500'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'exists:products,id'],
            'items.*.qty'          => ['required', 'numeric', 'min:0.001'],
            'items.*.presentacion' => ['nullable', 'in:KILOS,PIEZAS,CAJAS'],
            'items.*.comentarios'  => ['nullable', 'string', 'max:200'],
        ]);

        $cambios = null;

        DB::transaction(function () use ($transfer, $data, &$cambios) {
            // diff() debe capturarse entre fill() y save() — después de
            // guardar, Eloquent sincroniza los "originales" y getDirty() ya
            // no ve nada.
            $transfer->fill([
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id'   => $data['to_warehouse_id'],
                'fecha'             => $data['fecha'],
                'notas'             => $data['notas'] ?? null,
            ]);
            $cambios = $this->log->diff($transfer);
            $transfer->save();

            // Se reemplazan las partidas completas en vez de intentar
            // hacer match uno a uno — más simple y suficiente porque el
            // traspaso sigue PENDIENTE (nada de esto movió inventario
            // todavía).
            $transfer->items()->delete();
            foreach ($data['items'] as $it) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id'        => $it['product_id'],
                    'qty'               => $it['qty'],
                    'presentacion'      => $it['presentacion'] ?? null,
                    'comentarios'       => $it['comentarios'] ?? null,
                ]);
            }
        });

        $this->log->log($transfer, 'EDITADO', null, null, null, 'Traspaso editado (corrección de datos/partidas).', $cambios ?: null);
        return redirect()->route('admin.stock.transfers.show', $transfer)
            ->with('swal', ['icon' => 'success', 'title' => 'Actualizado', 'text' => 'Traspaso actualizado correctamente.']);
    }

    public function show(StockTransfer $transfer)
    {
        $transfer->load(['fromWarehouse', 'toWarehouse', 'items.product', 'creator', 'dispatch']);

        $statusClasses = [
            'PENDIENTE'  => 'bg-gray-100 text-gray-700',
            'ASIGNADO'   => 'bg-sky-100 text-sky-700',
            'EN_RUTA'    => 'bg-violet-100 text-violet-700',
            'COMPLETADO' => 'bg-emerald-100 text-emerald-700',
            'CANCELADO'  => 'bg-rose-100 text-rose-700',
        ];

        $canCompleteDirect = (bool) \App\Models\SystemSetting::get('logistica.permitir_completar_traspaso_directo', false);

        return view('admin.stock.transfers.show', compact('transfer', 'statusClasses', 'canCompleteDirect'));
    }

    public function cancel(StockTransfer $transfer)
    {
        if (!in_array($transfer->status, ['PENDIENTE', 'ASIGNADO'])) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Solo PENDIENTE o ASIGNADO pueden cancelarse.']);
        }

        $old = $transfer->status;
        $transfer->update(['status' => 'CANCELADO']);
        $this->log->log($transfer, 'CAMBIO_ESTADO', $old, 'CANCELADO');
        return back()->with('swal', ['icon' => 'success', 'title' => 'Cancelado', 'text' => 'Traspaso cancelado.']);
    }

    /**
     * Completar manualmente (sin despacho) — descuenta origen, suma destino.
     */
    public function complete(StockTransfer $transfer, InventoryService $inv)
    {
        if (!\App\Models\SystemSetting::get('logistica.permitir_completar_traspaso_directo', false)) {
            return back()->with('swal', [
                'icon'  => 'error',
                'title' => 'No permitido',
                'text'  => 'Completar traspasos directamente está deshabilitado. Este traspaso debe asignarse a un despacho y salir a ruta. Un superadmin puede habilitarlo en Superadmin → Configuración.',
            ]);
        }

        if (!in_array($transfer->status, ['PENDIENTE', 'ASIGNADO', 'EN_RUTA'])) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'No permitido', 'text' => 'Este traspaso no puede completarse.']);
        }

        $transfer->load('items');

        DB::transaction(function () use ($transfer, $inv) {
            foreach ($transfer->items as $it) {
                // Salida del origen
                $inv->stockOut(
                    productId:   $it->product_id,
                    warehouseId: $transfer->from_warehouse_id,
                    qty:         $it->qty,
                    motivo:      'TRASPASO_SALIDA',
                    referencia:  $transfer,
                    userId:      auth()->id(),
                );
                // Entrada al destino
                $inv->stockIn(
                    productId:   $it->product_id,
                    warehouseId: $transfer->to_warehouse_id,
                    qty:         $it->qty,
                    motivo:      'TRASPASO_ENTRADA',
                    referencia:  $transfer,
                    userId:      auth()->id(),
                );
            }

            $transfer->update([
                'status'        => 'COMPLETADO',
                'completado_at' => now(),
            ]);
        });

        $this->log->log($transfer, 'CAMBIO_ESTADO', 'PENDIENTE', 'COMPLETADO');
        return back()->with('swal', ['icon' => 'success', 'title' => 'Completado', 'text' => 'Stock transferido correctamente.']);
    }

    /**
     * Vista de impresión.
     */
    public function print(StockTransfer $transfer)
    {
        $transfer->load(['fromWarehouse', 'toWarehouse', 'items.product', 'creator', 'dispatch.driver']);
        return view('admin.stock.transfers.print', compact('transfer'));
    }
}