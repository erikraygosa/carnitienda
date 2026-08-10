<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductWarehousePrice;
use App\Models\Warehouse;
use App\Services\DocumentLogService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PriceByWarehouseController extends Controller implements HasMiddleware
{
    public function __construct(private PricingService $pricing, private DocumentLogService $log) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver precios almacen', only: ['index']),
            new Middleware('can:editar precios almacen', only: ['store']),
            new Middleware('can:aplicar precios matriz', only: ['aplicarMatriz']),
        ];
    }

    public function index(Request $request)
    {
        $warehouses  = Warehouse::orderBy('nombre')->get();
        $matriz      = Warehouse::where('is_primary', 1)->first() ?? $warehouses->first();
        $warehouseId = (int) $request->get('warehouse_id', $matriz?->id);

        $search = trim((string) $request->get('search', ''));

        $products = Product::where('activo', 1)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            }))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sku', 'precio_base']);

        $overrides = ProductWarehousePrice::where('warehouse_id', $warehouseId)
            ->pluck('precio', 'product_id');

        $modoAlmacen = $this->pricing->modoAlmacen();

        return view('admin.precios.index', compact(
            'warehouses', 'warehouseId', 'products', 'overrides', 'search', 'modoAlmacen', 'matriz'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'warehouse_id'            => ['required', 'exists:warehouses,id'],
            'precios'                 => ['required', 'array'],
            'precios.*'               => ['nullable', 'numeric', 'min:0'],
        ]);

        $cambios = 0;

        DB::transaction(function () use ($data, &$cambios) {
            foreach ($data['precios'] as $productId => $precio) {
                $product = Product::find($productId);
                if (! $product) continue;

                // Campo vacío = "quitar el precio propio, volver a usar el
                // de la ficha del producto (respaldo)".
                if ($precio === null || $precio === '') {
                    $existente = ProductWarehousePrice::where('product_id', $productId)
                        ->where('warehouse_id', $data['warehouse_id'])
                        ->first();
                    if ($existente) {
                        $this->log->log($product, 'PRECIO_ALMACEN_ELIMINADO', (string) $existente->precio, null, null,
                            "Almacén #{$data['warehouse_id']}: se quitó el precio propio, vuelve a usar el de la ficha.");
                        $existente->delete();
                        $cambios++;
                    }
                    continue;
                }

                $existente = ProductWarehousePrice::where('product_id', $productId)
                    ->where('warehouse_id', $data['warehouse_id'])
                    ->first();

                if ($existente && (float) $existente->precio === (float) $precio) {
                    continue; // sin cambios reales
                }

                $old = $existente?->precio;

                ProductWarehousePrice::updateOrCreate(
                    ['product_id' => $productId, 'warehouse_id' => $data['warehouse_id']],
                    ['precio' => $precio, 'updated_by' => auth()->id()]
                );

                $this->log->log($product, 'PRECIO_ALMACEN_ACTUALIZADO', $old !== null ? (string) $old : null, (string) $precio, null,
                    "Almacén #{$data['warehouse_id']}");
                $cambios++;
            }
        });

        return back()->with('swal', [
            'icon'  => 'success',
            'title' => 'Precios guardados',
            'text'  => $cambios > 0 ? "{$cambios} precio(s) actualizados." : 'No hubo cambios.',
        ]);
    }

    public function aplicarMatriz(Request $request)
    {
        $creados = $this->pricing->aplicarPreciosDeMatriz(auth()->id());

        return back()->with('swal', [
            'icon'  => $creados > 0 ? 'success' : 'info',
            'title' => $creados > 0 ? 'Precios aplicados' : 'Nada que aplicar',
            'text'  => $creados > 0
                ? "Se copiaron {$creados} precio(s) de Matriz a los demás almacenes (solo donde no había un precio propio configurado)."
                : 'Matriz no tiene precios propios configurados, o todos los demás almacenes ya tienen su propio precio en cada producto.',
        ]);
    }
}
