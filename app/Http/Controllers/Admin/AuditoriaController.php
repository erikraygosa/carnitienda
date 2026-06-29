<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentActivityLog;
use App\Models\Invoice;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AuditoriaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:ver auditoria')];
    }

    protected array $tiposMap = [
        'producto'    => Product::class,
        'pedido'      => SalesOrder::class,
        'factura'     => Invoice::class,
        'movimiento'  => StockMovement::class,
        'traspaso'    => StockTransfer::class,
        'inventario'  => InventoryMovement::class,
    ];

    public function index(Request $request)
    {
        $tipo    = $request->input('tipo');
        $userId  = $request->input('user_id');
        $action  = $request->input('action');
        $desde   = $request->input('desde');
        $hasta   = $request->input('hasta');

        $logs = DocumentActivityLog::with('user')
            ->when($tipo && isset($this->tiposMap[$tipo]),
                fn($q) => $q->where('document_type', $this->tiposMap[$tipo]))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($action, fn($q) => $q->where('action', $action))
            ->when($desde,  fn($q) => $q->whereDate('created_at', '>=', $desde))
            ->when($hasta,  fn($q) => $q->whereDate('created_at', '<=', $hasta))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $usuarios = User::orderBy('name')->get(['id','name']);
        $tiposInvertido = array_flip($this->tiposMap);

        return view('admin.auditoria.index', compact('logs', 'usuarios', 'tiposInvertido'));
    }
}
