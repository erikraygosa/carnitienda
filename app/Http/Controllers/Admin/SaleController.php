<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Client;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\CashRegister;
use App\Models\PaymentType;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\SaleNoteMailable;
use App\Services\WhatsappSender;
use App\Services\ArService;
use App\Services\CashService;
use App\Services\InventoryService;
use App\Services\DocumentLogService;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SaleController extends Controller implements HasMiddleware
{
    public function __construct(
        private DocumentLogService $log,
        private InventoryService $inv,
        private CashService $cashSvc,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver pedidos', only: ['index', 'create']),
            new Middleware('can:crear pedidos', only: ['store']),
            new Middleware('can:editar pedidos', only: ['edit', 'update', 'approve', 'startPreparing', 'process', 'dispatchToRoute', 'deliver', 'notDelivered', 'recordCash', 'settleDriver', 'sendForm', 'send', 'pdf', 'pdfDownload', 'ticket', 'ticketPdf']),
            new Middleware('can:cancelar pedidos', only: ['cancel', 'destroy']),
        ];
    }

    public function index()
    {
        return view('admin.sales.index');
    }

    public function create(Request $request)
    {
        $clients = Client::orderBy('nombre')->get([
            'id','nombre','email','telefono',
            'shipping_route_id','price_list_id',
            'credito_dias','credito_limite',
            'entrega_igual_fiscal',
            'fiscal_calle','fiscal_numero','fiscal_colonia','fiscal_ciudad','fiscal_estado','fiscal_cp',
            'entrega_calle','entrega_numero','entrega_colonia','entrega_ciudad','entrega_estado','entrega_cp',
        ]);

        $priceLists   = PriceList::orderBy('nombre')->get(['id','nombre']);
        $products     = Product::where('activo', 1)->orderBy('nombre')->get(['id','nombre','precio_base','sku','unidad']);
        $productsJson = $products->map(fn($p) => [
            'id'     => $p->id,
            'nombre' => $p->nombre,
            'sku'    => $p->sku ?? '',
            'precio' => (float) $p->precio_base,
            'unidad' => $p->unidad ?? '',
        ])->values();
        $warehouses   = Warehouse::orderBy('nombre')->get(['id','nombre']);
        // Almacén por default: MATRIZ (el marcado is_primary), igual que Pedidos.
        $mainWarehouseId = DB::table('warehouses')->where('is_primary', 1)->value('id')
            ?? DB::table('warehouses')->orderBy('id')->value('id');
        // Venta directa de mostrador: se amarra a una caja (cash_registers)
        // ya ABIERTA — la misma que usan Cajas/POS, no el PosRegister viejo.
        $cashRegisters = CashRegister::with(['warehouse:id,nombre', 'user:id,name'])
            ->where('estatus', 'ABIERTO')
            ->orderByDesc('id')
            ->get();

        $payTypes = PaymentType::query()
            ->select('id','clave','descripcion')
            ->orderBy('clave')
            ->get()
            ->map(fn($p) => (object)[
                'id'    => $p->id,
                'clave' => $p->clave,
                'label' => $p->descripcion ?: $p->clave,
            ]);

        $overrides = DB::table('client_price_overrides')
            ->select('client_id','product_id','precio')
            ->whereIn('client_id', $clients->pluck('id'))
            ->get()
            ->groupBy('client_id')
            ->map(fn($rows) => $rows->pluck('precio','product_id')->map(fn($v) => (float)$v)->toArray())
            ->toArray();

        $listItems = DB::table('price_list_items')
            ->select('price_list_id','product_id','precio')
            ->whereIn('price_list_id', $priceLists->pluck('id'))
            ->get()
            ->groupBy('price_list_id')
            ->map(fn($rows) => $rows->pluck('precio','product_id')->map(fn($v) => (float)$v)->toArray())
            ->toArray();

        $clientDefaults = $clients->mapWithKeys(fn($c) => [(string)$c->id => [
            'shipping_route_id' => (string) ($c->shipping_route_id ?? ''),
            'price_list_id'     => (string) ($c->price_list_id ?? ''),
            'credito_dias'      => (int)    ($c->credito_dias  ?? 0),
            'credito_limite'    => (float)  ($c->credito_limite ?? 0),
            'telefono'          => (string) ($c->telefono ?? ''),
            'entrega_calle'    => $c->entrega_igual_fiscal ? ($c->fiscal_calle   ?? '') : ($c->entrega_calle   ?? ''),
            'entrega_numero'   => $c->entrega_igual_fiscal ? ($c->fiscal_numero  ?? '') : ($c->entrega_numero  ?? ''),
            'entrega_colonia'  => $c->entrega_igual_fiscal ? ($c->fiscal_colonia ?? '') : ($c->entrega_colonia ?? ''),
            'entrega_ciudad'   => $c->entrega_igual_fiscal ? ($c->fiscal_ciudad  ?? '') : ($c->entrega_ciudad  ?? ''),
            'entrega_estado'   => $c->entrega_igual_fiscal ? ($c->fiscal_estado  ?? '') : ($c->entrega_estado  ?? ''),
            'entrega_cp'       => $c->entrega_igual_fiscal ? ($c->fiscal_cp      ?? '') : ($c->entrega_cp      ?? ''),
        ]])->toArray();

        return view('admin.sales.create', compact(
            'clients','priceLists','products','productsJson','warehouses','mainWarehouseId',
            'cashRegisters','payTypes','overrides','listItems','clientDefaults'
        ));
    }

    /**
     * Precio "oficial" para un producto dado el cliente/lista de precios de
     * la venta — misma resolución que hace el JS del formulario (override
     * del cliente, o precio de la lista elegida). Ver el mismo método en
     * SalesOrderController para el detalle de cada caso.
     */
    private function precioOficial(?int $clientId, ?int $priceListId, ?int $productId): ?float
    {
        if (!$productId) return null;

        if ($priceListId) {
            $precio = DB::table('price_list_items')
                ->where('price_list_id', $priceListId)
                ->where('product_id', $productId)
                ->value('precio');
            return round((float) ($precio ?? 0), 4);
        }

        if ($clientId) {
            $precio = DB::table('client_price_overrides')
                ->where('client_id', $clientId)
                ->where('product_id', $productId)
                ->value('precio');

            if ($precio === null || (float) $precio <= 0) {
                return null; // sin override todavía — se deja capturar (registrarPreciosNuevos)
            }

            return round((float) $precio, 4);
        }

        return null;
    }

    private function aplicarPreciosOficiales(array $items, ?int $clientId, ?int $priceListId): array
    {
        foreach ($items as $i => $it) {
            $oficial = $this->precioOficial($clientId, $priceListId, $it['product_id'] ?? null);
            if ($oficial !== null) {
                $items[$i]['precio'] = $oficial;
            }
        }
        return $items;
    }

    /**
     * Cuando el cliente todavía NO tiene precio para un producto (modo
     * "precio del cliente", sin lista de precios elegida), cualquiera puede
     * capturarlo directo en la línea de la venta — queda registrado como el
     * precio oficial del cliente para ese producto. Igual que en Pedidos.
     */
    private function registrarPreciosNuevos(array $items, ?int $clientId, ?int $priceListId): void
    {
        if (!$clientId || $priceListId) return;

        foreach ($items as $it) {
            $productId = $it['product_id'] ?? null;
            $precio    = (float) ($it['precio'] ?? 0);
            if (!$productId || $precio <= 0) continue;

            $existente = DB::table('client_price_overrides')
                ->where('client_id', $clientId)
                ->where('product_id', $productId)
                ->value('precio');

            if ($existente !== null && (float) $existente > 0) continue; // ya existía, no se toca aquí

            DB::table('client_price_overrides')->updateOrInsert(
                ['client_id' => $clientId, 'product_id' => $productId],
                ['precio' => round($precio, 4), 'updated_at' => now(), 'created_at' => now()]
            );

            $client  = Client::find($clientId);
            $product = Product::find($productId);
            if ($client) {
                $this->log->log(
                    $client,
                    'PRECIO_PERSONALIZADO_ACTUALIZADO',
                    null,
                    number_format($precio, 2),
                    null,
                    'Precio asignado al crear una nota de venta (no tenía precio configurado)'
                        . ($product ? " — Producto: {$product->nombre}" : ''),
                    ['product_id' => $productId, 'producto' => $product->nombre ?? null, 'old' => null, 'new' => $precio],
                );
            }
        }
    }

    /**
     * Venta directa de mostrador: no pasa por Aprobar/Preparar/En ruta —
     * se crea ya COMPLETADA, descontando inventario (vía la misma ruta que
     * usa el Panel de Surtido, para que productos compuestos/subproducto se
     * resuelvan bien) y cargando la CxC del cliente si es a crédito.
     */
    public function store(Request $request, InventoryService $inv)
    {
        if ($request->input('price_list_id') === 'client') {
            $request->merge(['price_list_id' => null]);
        }

        $data = $request->validate([
            'fecha'             => ['required','date'],
            'cash_register_id'  => ['required','exists:cash_registers,id'],
            'warehouse_id'      => ['required','exists:warehouses,id'],
            'client_id'         => ['nullable','exists:clients,id'],
            'payment_type_id'   => ['nullable','exists:payment_types,id'],
            'price_list_id'     => ['nullable','exists:price_lists,id'],
            'moneda'            => ['required','string','max:10'],
            'tipo_venta'        => ['required','in:CONTADO,CREDITO'],
            'credit_days'       => ['nullable','integer','min:0'],
            'items'                 => ['required','array','min:1'],
            'items.*.product_id'    => ['required','exists:products,id'],
            'items.*.descripcion'   => ['required','string','max:255'],
            'items.*.cantidad'      => ['required','numeric','gt:0'],
            'items.*.num_cajas'     => ['nullable','integer','min:0'],
            'items.*.precio'        => ['required','numeric','gte:0'],
            'items.*.descuento'     => ['nullable','numeric','gte:0'],
            'items.*.impuesto'      => ['nullable','numeric','gte:0'],
            'comentarios'           => ['nullable','string','max:2000'],
        ]);

        $data['items'] = $this->aplicarPreciosOficiales(
            $data['items'], $data['client_id'] ?? null, $data['price_list_id'] ?? null
        );
        $this->registrarPreciosNuevos(
            $data['items'], $data['client_id'] ?? null, $data['price_list_id'] ?? null
        );

        $cashRegister = CashRegister::find($data['cash_register_id']);
        if (!$cashRegister || $cashRegister->estatus !== 'ABIERTO') {
            return back()->withErrors(['cash_register_id' => 'Esa caja ya no está abierta — elige una caja abierta.'])->withInput();
        }

        if ($data['tipo_venta'] === 'CREDITO' && empty($data['client_id'])) {
            return back()->withErrors(['client_id' => 'Una venta a crédito necesita cliente.'])->withInput();
        }

        $sale = null;

        DB::transaction(function () use (&$sale, $data, $cashRegister, $inv) {
            $subtotal=0; $descuento=0; $impuestos=0; $total=0;
            foreach ($data['items'] as $it) {
                $line_sub  = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc = (float)($it['descuento'] ?? 0);
                $line_tax  = (float)($it['impuesto']  ?? 0);
                $line_tot  = max($line_sub - $line_desc, 0) + $line_tax;
                $subtotal += $line_sub; $descuento += $line_desc; $impuestos += $line_tax; $total += $line_tot;
            }

            $nextId = (Sale::max('id') ?? 0) + 1;
            $folio  = 'NV-'.now()->format('Ymd').'-'.str_pad((string)$nextId, 4, '0', STR_PAD_LEFT);

            $sale = Sale::create([
                'folio'               => $folio,
                'fecha'               => $data['fecha'],
                'cash_register_id'    => $cashRegister->id,
                'warehouse_id'        => $data['warehouse_id'],
                'client_id'           => $data['client_id'] ?? null,
                'payment_type_id'     => $data['payment_type_id'] ?? null,
                'price_list_id'       => $data['price_list_id'] ?? null,
                'moneda'              => $data['moneda'],
                'tipo_venta'          => $data['tipo_venta'],
                'credit_days'         => $data['tipo_venta']==='CREDITO' ? ($data['credit_days'] ?? 0) : null,
                'delivery_type'       => 'RECOGER',
                'subtotal'            => $subtotal,
                'impuestos'           => $impuestos,
                'descuento'           => $descuento,
                'total'               => $total,
                'status'              => Sale::S_COMPLETADA,
                'user_id'             => auth()->id(),
                'entregado_at'        => now(),
                'comentarios'         => $data['comentarios'] ?? null,
            ]);

            foreach ($data['items'] as $it) {
                $line_sub  = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc = (float)($it['descuento'] ?? 0);
                $line_tax  = (float)($it['impuesto']  ?? 0);
                $line_tot  = max($line_sub - $line_desc, 0) + $line_tax;

                SaleItem::create([
                    'sale_id'     => $sale->id,
                    'product_id'  => $it['product_id'],
                    'descripcion' => $it['descripcion'],
                    'cantidad'    => $it['cantidad'],
                    'num_cajas'   => $it['num_cajas'] ?? null,
                    'precio'      => $it['precio'],
                    'descuento'   => $line_desc,
                    'impuesto'    => $line_tax,
                    'total'       => $line_tot,
                ]);

                // Descuenta inventario ya — misma ruta que Salida de Producto,
                // para que subproducto/BOM se resuelvan sobre el producto real.
                $inv->consumeForOrderItem(
                    (object) ['product_id' => $it['product_id'], 'cantidad' => $it['cantidad']],
                    $data['warehouse_id'],
                    $sale,
                    auth()->id()
                );
            }

            if ($data['tipo_venta'] === 'CREDITO') {
                app(ArService::class)->charge(
                    clientId: (int) $data['client_id'],
                    monto:    $total,
                    desc:     "Nota de venta {$sale->folio}",
                    source:   $sale,
                    fecha:    now()->toDateString(),
                );
            } else {
                app(CashService::class)->registerCashSale($cashRegister, $total);
            }
        });

        return redirect()->route('admin.sales.edit', $sale)
            ->with('swal',['icon'=>'success','title'=>'Nota completada','text'=>'Se descontó el inventario y quedó registrada la venta.']);
    }

    public function edit(Request $request, Sale $sale)
    {
        $sale->load('items.product','client','priceList','warehouse','cashRegister','paymentType');

        $clients = Client::orderBy('nombre')->get([
            'id','nombre','email','telefono',
            'shipping_route_id','price_list_id',
            'credito_dias','credito_limite',
            'entrega_igual_fiscal',
            'fiscal_calle','fiscal_numero','fiscal_colonia','fiscal_ciudad','fiscal_estado','fiscal_cp',
            'entrega_calle','entrega_numero','entrega_colonia','entrega_ciudad','entrega_estado','entrega_cp',
        ]);

        $priceLists   = PriceList::orderBy('nombre')->get(['id','nombre']);
        $products     = Product::where('activo', 1)->orderBy('nombre')->get(['id','nombre','precio_base','sku','unidad']);
        $productsJson = $products->map(fn($p) => [
            'id'     => $p->id,
            'nombre' => $p->nombre,
            'sku'    => $p->sku ?? '',
            'precio' => (float) $p->precio_base,
            'unidad' => $p->unidad ?? '',
        ])->values();
        $warehouses   = Warehouse::orderBy('nombre')->get(['id','nombre']);

        // Cajas abiertas + la propia caja de esta nota (aunque ya esté
        // CERRADA) para que siga apareciendo seleccionada en el dropdown.
        $cashRegisters = CashRegister::with(['warehouse:id,nombre', 'user:id,name'])
            ->where(fn ($q) => $q->where('estatus', 'ABIERTO')->orWhere('id', $sale->cash_register_id))
            ->orderByDesc('id')
            ->get();

        $payTypes = PaymentType::query()
            ->select('id','clave','descripcion')
            ->orderBy('clave')
            ->get()
            ->map(fn($p) => (object)[
                'id'    => $p->id,
                'clave' => $p->clave,
                'label' => $p->descripcion ?: $p->clave,
            ]);

        $overrides = DB::table('client_price_overrides')
            ->select('client_id','product_id','precio')
            ->whereIn('client_id', $clients->pluck('id'))
            ->get()
            ->groupBy('client_id')
            ->map(fn($rows) => $rows->pluck('precio','product_id')->map(fn($v) => (float)$v)->toArray())
            ->toArray();

        $listItems = DB::table('price_list_items')
            ->select('price_list_id','product_id','precio')
            ->whereIn('price_list_id', $priceLists->pluck('id'))
            ->get()
            ->groupBy('price_list_id')
            ->map(fn($rows) => $rows->pluck('precio','product_id')->map(fn($v) => (float)$v)->toArray())
            ->toArray();

        // El desbloqueo de una nota COMPLETADA solo aplica viniendo del
        // módulo Gestión de notas — igual que ya se hace en Pedidos.
        $puedeEditarCerrados = $request->query('origen') === 'gestion-notas'
            && auth()->user()->can('editar pedidos cerrados');

        return view('admin.sales.edit', compact(
            'sale','clients','priceLists','products','productsJson','warehouses',
            'cashRegisters','payTypes','overrides','listItems','puedeEditarCerrados'
        ));
    }

    public function update(Request $request, Sale $sale)
    {
        // Mismo criterio que en Pedidos: el desbloqueo de una nota COMPLETADA
        // solo aplica viniendo del módulo Gestión de notas, no del flujo
        // normal de Notas de venta → Editar aunque el usuario tenga el permiso.
        $puedeEditarCerrados = $request->input('origen') === 'gestion-notas'
            && auth()->user()->can('editar pedidos cerrados');
        $editandoCerrado = $puedeEditarCerrados && $sale->status === Sale::S_COMPLETADA;

        if ($sale->status !== 'BORRADOR' && !$editandoCerrado) {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo puede editarse una nota en BORRADOR (o una completada, desde Gestión de notas).']);
        }

        if ($request->input('price_list_id') === 'client') {
            $request->merge(['price_list_id' => null]);
        }

        $data = $request->validate([
            'fecha'            => ['required','date'],
            'cash_register_id' => ['required','exists:cash_registers,id'],
            'warehouse_id'     => ['required','exists:warehouses,id'],
            'client_id'        => ['nullable','exists:clients,id'],
            'payment_type_id'  => ['nullable','exists:payment_types,id'],
            'price_list_id'    => ['nullable','exists:price_lists,id'],
            'moneda'           => ['required','string','max:10'],
            'tipo_venta'       => ['required','in:CONTADO,CREDITO'],
            'credit_days'      => ['nullable','integer','min:0'],
            'items'                 => ['required','array','min:1'],
            'items.*.product_id'    => ['required','exists:products,id'],
            'items.*.descripcion'   => ['required','string','max:255'],
            'items.*.cantidad'      => ['required','numeric','gt:0'],
            'items.*.num_cajas'     => ['nullable','integer','min:0'],
            'items.*.precio'        => ['required','numeric','gte:0'],
            'items.*.descuento'     => ['nullable','numeric','gte:0'],
            'items.*.impuesto'      => ['nullable','numeric','gte:0'],
            'comentarios'           => ['nullable','string','max:2000'],
        ]);

        if ($data['tipo_venta'] === 'CREDITO' && empty($data['client_id'])) {
            return back()->withErrors(['client_id' => 'Una venta a crédito necesita cliente.'])->withInput();
        }

        $data['items'] = $this->aplicarPreciosOficiales(
            $data['items'], $data['client_id'] ?? null, $data['price_list_id'] ?? null
        );
        $this->registrarPreciosNuevos(
            $data['items'], $data['client_id'] ?? null, $data['price_list_id'] ?? null
        );

        // Snapshot ANTES de tocar nada — para ajustar CxC/caja por la
        // diferencia si se está corrigiendo una nota ya COMPLETADA.
        $totalAntes     = (float) $sale->total;
        $tipoVentaAntes = $sale->tipo_venta;

        DB::transaction(function () use ($sale, $data) {
            $subtotal=0; $descuento=0; $impuestos=0; $total=0;

            $sale->items()->delete();

            foreach ($data['items'] as $it) {
                $line_sub  = (float)$it['cantidad'] * (float)$it['precio'];
                $line_desc = (float)($it['descuento'] ?? 0);
                $line_tax  = (float)($it['impuesto']  ?? 0);
                $line_tot  = max($line_sub - $line_desc, 0) + $line_tax;

                $subtotal += $line_sub; $descuento += $line_desc; $impuestos += $line_tax; $total += $line_tot;

                SaleItem::create([
                    'sale_id'     => $sale->id,
                    'product_id'  => $it['product_id'],
                    'descripcion' => $it['descripcion'],
                    'cantidad'    => $it['cantidad'],
                    'num_cajas'   => $it['num_cajas'] ?? null,
                    'precio'      => $it['precio'],
                    'descuento'   => $line_desc,
                    'impuesto'    => $line_tax,
                    'total'       => $line_tot,
                ]);
            }

            $sale->update([
                'fecha'            => $data['fecha'],
                'comentarios'      => $data['comentarios'] ?? null,
                'cash_register_id' => $data['cash_register_id'],
                'warehouse_id'     => $data['warehouse_id'],
                'client_id'        => $data['client_id'] ?? null,
                'payment_type_id'  => $data['payment_type_id'] ?? null,
                'price_list_id'    => $data['price_list_id'] ?? null,
                'moneda'           => $data['moneda'],
                'tipo_venta'       => $data['tipo_venta'],
                'credit_days'      => $data['tipo_venta']==='CREDITO' ? ($data['credit_days'] ?? 0) : null,
                'subtotal'         => $subtotal,
                'impuestos'        => $impuestos,
                'descuento'        => $descuento,
                'total'            => $total,
            ]);
        });

        if (!$editandoCerrado) {
            return back()->with('swal',['icon'=>'success','title'=>'Actualizada','text'=>'Nota de venta actualizada.']);
        }

        // Corrección de una nota ya COMPLETADA (permiso de Gestión de
        // notas): el stock y (si era crédito) la CxC, o la caja (si era de
        // contado), ya se habían movido al crearse.
        //
        // Igual que en Pedidos: no se puede calcular el ajuste de stock
        // comparando cantidades por product_id — si un producto es
        // subproducto/compuesto (BOM) lo que en realidad se descontó fue el
        // padre/los componentes. Por eso se revierten los movimientos de
        // stock REALES que ya existan para esta nota y se vuelven a aplicar
        // desde cero con las partidas nuevas.
        $sale->refresh();

        $movimientosPrevios = StockMovement::where('referencia_type', Sale::class)
            ->where('referencia_id', $sale->id)
            ->get();
        foreach ($movimientosPrevios as $m) {
            if ($m->tipo === 'OUT') {
                $this->inv->stockIn((int) $m->product_id, (int) $m->warehouse_id, (float) $m->cantidad, 'AJUSTE_EDICION_CERRADA', $sale, auth()->id());
            } elseif ($m->tipo === 'IN') {
                $this->inv->stockOut((int) $m->product_id, (int) $m->warehouse_id, (float) $m->cantidad, 'AJUSTE_EDICION_CERRADA', $sale, auth()->id());
            }
        }
        foreach ($sale->items()->get() as $itemNuevo) {
            if (!$itemNuevo->product_id || (float) $itemNuevo->cantidad <= 0) continue;
            $this->inv->consumeForOrderItem($itemNuevo, $sale->warehouse_id, $sale, auth()->id());
        }

        // Ajustar CxC / caja — el tipo de venta también pudo haber cambiado
        // (de contado a crédito o viceversa), no solo el total.
        $deltaTotal = round((float) $sale->total - $totalAntes, 2);
        $ar = fn () => app(ArService::class);

        if ($tipoVentaAntes === 'CREDITO' && $sale->tipo_venta === 'CREDITO') {
            if ($sale->client_id && abs($deltaTotal) >= 0.01) {
                $ar()->charge(clientId: $sale->client_id, monto: $deltaTotal, desc: "Ajuste por edición de nota cerrada {$sale->folio}", source: $sale, fecha: now()->toDateString());
            }
        } elseif ($tipoVentaAntes === 'CREDITO' && $sale->tipo_venta !== 'CREDITO') {
            if ($sale->client_id) {
                $ar()->charge(clientId: $sale->client_id, monto: -1 * $totalAntes, desc: "Reversa por cambio a contado al editar nota cerrada {$sale->folio}", source: $sale, fecha: now()->toDateString());
            }
            $this->ajustarCajaAbierta($sale, (float) $sale->total);
        } elseif ($tipoVentaAntes !== 'CREDITO' && $sale->tipo_venta === 'CREDITO') {
            $this->ajustarCajaAbierta($sale, -1 * $totalAntes);
            if ($sale->client_id) {
                $ar()->charge(clientId: $sale->client_id, monto: (float) $sale->total, desc: "Cargo por cambio a crédito al editar nota cerrada {$sale->folio}", source: $sale, fecha: now()->toDateString());
            }
        } elseif (abs($deltaTotal) >= 0.01) {
            $this->ajustarCajaAbierta($sale, $deltaTotal);
        }

        $this->log->log(
            $sale, 'EDITADO_CERRADO', 'COMPLETADA', 'COMPLETADA',
            note: sprintf(
                'Editado desde Gestión de notas. Total: $%s → $%s.%s',
                number_format($totalAntes, 2), number_format((float) $sale->total, 2),
                $sale->tipo_venta === 'CREDITO' ? ' Ajuste CxC aplicado.' : ' Ajuste de caja aplicado (si seguía abierta).'
            )
        );

        return back()->with('swal', ['icon' => 'success', 'title' => 'Nota corregida', 'text' => 'Se actualizó la nota y se ajustó el inventario (y la CxC o la caja, si aplicaba).']);
    }

    /**
     * Ajusta las ventas en efectivo de la caja donde se registró la nota,
     * solo si esa caja sigue ABIERTA (si ya se cerró, no se toca un corte
     * ya hecho — queda para conciliar aparte, igual que en cancelarPos()).
     */
    private function ajustarCajaAbierta(Sale $sale, float $monto): void
    {
        $register = $sale->cashRegister;
        if ($register && $register->estatus === 'ABIERTO') {
            $this->cashSvc->registerCashSale($register, $monto);
        }
    }

    public function destroy(Sale $sale)
    {
        if (!in_array($sale->status, ['BORRADOR','APROBADO','PREPARANDO'])) {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo estados iniciales pueden eliminarse.']);
        }
        $sale->items()->delete();
        $sale->delete();

        return redirect()->route('admin.sales.index')
            ->with('swal',['icon'=>'success','title'=>'Eliminada','text'=>'Nota de venta eliminada.']);
    }

    // ====== Flujo de estados ======

    public function approve(Sale $sale)
    {
        if ($sale->status !== 'BORRADOR') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo BORRADOR puede aprobarse.']);
        }
        $sale->update(['status'=>'ABIERTA']);
        return back()->with('swal',['icon'=>'success','title'=>'Abierta','text'=>'Nota abierta.']);
    }

    public function startPreparing(Sale $sale)
    {
        if ($sale->status !== 'ABIERTA') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Debe estar ABIERTA.']);
        }
        $sale->update(['status'=>'PREPARANDO','preparado_at'=>now()]);
        return back()->with('swal',['icon'=>'success','title'=>'Preparando','text'=>'Nota en preparación.']);
    }

    public function process(Sale $sale)
    {
        if (!in_array($sale->status, ['ABIERTA','PREPARANDO'])) {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Debe estar ABIERTA o PREPARANDO.']);
        }

        DB::transaction(function () use ($sale) {
            foreach ($sale->items as $it) {
                StockMovement::create([
                    'warehouse_id'    => $sale->warehouse_id,
                    'product_id'      => $it->product_id,
                    'tipo'            => 'OUT',
                    'cantidad'        => $it->cantidad,
                    'motivo'          => 'Nota de venta #'.$sale->id,
                    'referencia_type' => Sale::class,
                    'referencia_id'   => $sale->id,
                    'user_id'         => auth()->id(),
                ]);
            }
            $sale->update(['status'=>'PROCESADA','despachado_at'=>now()]);
        });

        return back()->with('swal',['icon'=>'success','title'=>'Procesada','text'=>'Stock descontado y nota PROCESADA.']);
    }

    public function dispatchToRoute(Sale $sale)
    {
        if ($sale->status !== 'PROCESADA') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo PROCESADA puede salir a ruta.']);
        }
        if (!$sale->driver_id) {
            return back()->with('swal',['icon'=>'warning','title'=>'Sin chofer','text'=>'Asigna un chofer antes de enviar a ruta.']);
        }
        $sale->update(['status'=>'EN_RUTA','en_ruta_at'=>now()]);
        return back()->with('swal',['icon'=>'success','title'=>'En ruta','text'=>'La nota salió a ruta.']);
    }

    public function deliver(Sale $sale)
    {
        if ($sale->status !== 'EN_RUTA') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo EN_RUTA puede marcarse como ENTREGADA.']);
        }
        $sale->update(['status'=>'ENTREGADA','entregado_at'=>now()]);
        return back()->with('swal',['icon'=>'success','title'=>'Entregada','text'=>'Nota entregada.']);
    }

    public function notDelivered(Request $request, Sale $sale)
    {
        if ($sale->status !== 'EN_RUTA') {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'Solo EN_RUTA puede marcarse como NO_ENTREGADA.']);
        }
        $request->validate(['nota'=>'nullable|string|max:500']);
        $nota = $request->input('nota');

        DB::transaction(function () use ($sale, $nota) {
            $data = ['status'=>'NO_ENTREGADA','no_entregado_at'=>now()];
            if ($nota) $data['delivery_notes'] = trim(($sale->delivery_notes ?? '')."\n".$nota);
            $sale->update($data);
            $sale->increment('delivery_attempts');
        });

        return back()->with('swal',['icon'=>'success','title'=>'No entregada','text'=>'Se marcó como no entregada.']);
    }

    public function cancel(Sale $sale)
    {
        if (in_array($sale->status, ['EN_RUTA','ENTREGADA','CANCELADA'])) {
            return back()->with('swal',['icon'=>'error','title'=>'No permitido','text'=>'No se puede cancelar en este estado.']);
        }

        // Si ya estaba COMPLETADA, ya descontó inventario (y CxC si era
        // crédito) al crearse — hay que revertir eso exacto, no solo cambiar
        // el estatus. Se revierten los stock_movements REALES de esta nota
        // (no las partidas a ciegas) por lo mismo que ya se corrigió en
        // Pedidos: un producto subproducto/compuesto descuenta el padre o
        // sus componentes, no el producto de la partida.
        DB::transaction(function () use ($sale) {
            if ($sale->status === Sale::S_COMPLETADA) {
                $movimientos = StockMovement::where('referencia_type', Sale::class)
                    ->where('referencia_id', $sale->id)
                    ->get();
                $inv = app(InventoryService::class);
                foreach ($movimientos as $m) {
                    if ($m->tipo === 'OUT') {
                        $inv->stockIn((int) $m->product_id, (int) $m->warehouse_id, (float) $m->cantidad, 'CANCELACION_NOTA_VENTA', $sale, auth()->id());
                    } elseif ($m->tipo === 'IN') {
                        $inv->stockOut((int) $m->product_id, (int) $m->warehouse_id, (float) $m->cantidad, 'CANCELACION_NOTA_VENTA', $sale, auth()->id());
                    }
                }

                if ($sale->tipo_venta === 'CREDITO' && $sale->client_id) {
                    app(ArService::class)->charge(
                        clientId: $sale->client_id,
                        monto:    -1 * (float) $sale->total,
                        desc:     "Reversa por cancelación de nota de venta {$sale->folio}",
                        source:   $sale,
                        fecha:    now()->toDateString(),
                    );
                } elseif ($sale->cash_register_id) {
                    $cashRegister = $sale->cashRegister;
                    if ($cashRegister && $cashRegister->estatus === 'ABIERTO') {
                        app(CashService::class)->registerCashSale($cashRegister, -1 * (float) $sale->total);
                    }
                }
            }

            $sale->update(['status'=>'CANCELADA']);
        });

        return back()->with('swal',['icon'=>'success','title'=>'Cancelada','text'=>'Nota cancelada — se revirtió el inventario y, si aplicaba, la CxC o el efectivo de caja.']);
    }

    public function recordCash(Request $request, Sale $sale)
    {
        $request->validate(['monto'=>'required|numeric|min:0']);
        $monto = (float)$request->monto;

        DB::transaction(function () use ($sale, $monto) {
            $sale->update([
                'cobrado_efectivo'       => round(($sale->cobrado_efectivo ?? 0) + $monto, 2),
                'cobrado_confirmado_at'  => now(),
                'cobrado_confirmado_por' => auth()->id(),
            ]);
        });

        return back()->with('swal',['icon'=>'success','title'=>'Cobro registrado','text'=>'Se registró el cobro en efectivo.']);
    }

    public function settleDriver(Request $request, Sale $sale)
    {
        $request->validate(['pos_register_id'=>'nullable|exists:pos_registers,id']);
        $posRegisterId = $request->input('pos_register_id');

        DB::transaction(function () use ($sale, $posRegisterId) {
            $data = [
                'driver_settlement_status' => 'LIQUIDADO',
                'driver_settlement_at'     => now(),
            ];
            if ($posRegisterId) $data['pos_register_id'] = $posRegisterId;
            $sale->update($data);
        });

        return back()->with('swal',['icon'=>'success','title'=>'Liquidada','text'=>'Liquidación de chofer completada.']);
    }

    // ========= TICKET TÉRMICO (80mm) =========
    public function ticket(Sale $sale)
    {
        $sale->load('client', 'items.product', 'warehouse');
        $empresa = app(\App\Services\CompanyService::class)->activa();

        return view('admin.sales.ticket', compact('sale', 'empresa'));
    }

    public function ticketPdf(Sale $sale)
    {
        $sale->load('client', 'items.product', 'warehouse');
        $empresa = app(\App\Services\CompanyService::class)->activa();

        // Página de 80mm para que el ticket (72mm) tenga margen y no se
        // corte el contenido del lado derecho, igual que en Pedidos/POS.
        $ancho = 226.77;
        $alto  = 1200;

        $pdf = Pdf::loadView('admin.sales.ticket-pdf', compact('sale', 'empresa'))
            ->setPaper([0, 0, $ancho, $alto], 'portrait');

        return $pdf->stream('ticket-nota-' . ($sale->folio ?? $sale->id) . '.pdf');
    }

    public function pdf(Sale $sale)
    {
        $sale->load('client','items.product','warehouse','driver','route');
        $empresa = app(\App\Services\CompanyService::class)->activa();

        $pdf = Pdf::loadView('pdf.sales_note', [
            'sale'       => $sale,
            'empresa'    => $empresa,
            'mostrarIva' => \App\Models\SystemSetting::get('pedidos.mostrar_iva', true),
        ]);
        return $pdf->stream('nota-venta-'.$sale->id.'.pdf');
    }

    public function pdfDownload(Sale $sale)
    {
        $sale->load('client','items.product','warehouse','driver','route');
        $empresa = app(\App\Services\CompanyService::class)->activa();

        $pdf = Pdf::loadView('pdf.sales_note', [
            'sale'       => $sale,
            'empresa'    => $empresa,
            'mostrarIva' => \App\Models\SystemSetting::get('pedidos.mostrar_iva', true),
        ]);
        return $pdf->download('nota-venta-'.$sale->id.'.pdf');
    }

    public function sendForm(Sale $sale)
    {
        $sale->load('client');
        return view('admin.sales.send', [
            'sale'        => $sale,
            'clientEmail' => $sale->client->email ?? '',
            'clientPhone' => $sale->client->telefono ?? '',
        ]);
    }

    public function send(Request $request, Sale $sale, WhatsappSender $whatsapp)
    {
        $request->validate([
            'channels'   => ['required','array','min:1'],
            'channels.*' => ['in:email,whatsapp'],
            'formato'    => ['nullable','in:carta,ticket'],
            'email'      => ['nullable','email'],
            'telefono'   => ['nullable','string'],
            'mensaje'    => ['nullable','string','max:500'],
        ]);

        $sale->load('client','items.product','warehouse','driver','route');
        $empresa = app(\App\Services\CompanyService::class)->activa();
        $formato = $request->input('formato', 'carta');

        if ($formato === 'ticket') {
            $pdf = Pdf::loadView('admin.sales.ticket-pdf', ['sale' => $sale, 'empresa' => $empresa])
                ->setPaper([0, 0, 226.77, 1200], 'portrait');
            $fname = 'ticket-' . ($sale->folio ?? $sale->id) . '.pdf';
        } else {
            $pdf   = Pdf::loadView('pdf.sales_note', [
                'sale'       => $sale,
                'mostrarIva' => \App\Models\SystemSetting::get('pedidos.mostrar_iva', true),
            ]);
            $fname = 'nota-venta-' . ($sale->folio ?? $sale->id) . '.pdf';
        }
        $raw   = $pdf->output();

        $errors = [];

        if (in_array('email', $request->channels, true)) {
            $to = $request->input('email') ?: ($sale->client->email ?? null);
            if (!$to) {
                $errors[] = 'Sin email de cliente.';
            } else {
                try {
                    Mail::to($to)->send(new SaleNoteMailable($sale, $raw, $fname));
                } catch (\Throwable $e) {
                    $errors[] = 'Error email: '.$e->getMessage();
                }
            }
        }

        if (in_array('whatsapp', $request->channels, true)) {
            $phone = $request->input('telefono') ?: ($sale->client->telefono ?? null);
            $msg   = $request->input('mensaje', 'Te adjunto la nota de venta 📎');
            if (!$phone) {
                $errors[] = 'Sin teléfono de cliente.';
            } else {
                try {
                    $resp = $whatsapp->sendPdf($phone, $msg, $fname, $raw);
                    if (!($resp['ok'] ?? false)) {
                        $errors[] = 'WhatsApp: '.json_encode($resp['body'] ?? []);
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Error WhatsApp: '.$e->getMessage();
                }
            }
        }

        if ($errors) {
            return back()->with('swal',['icon'=>'error','title'=>'Envío parcial','text'=>implode(' | ', $errors)]);
        }

        return back()->with('swal',['icon'=>'success','title'=>'Enviado','text'=>'Nota enviada correctamente.']);
    }
}