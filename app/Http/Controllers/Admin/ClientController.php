<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ShippingRoute;
use App\Models\PaymentType;
use App\Models\PriceList;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientController extends Controller
{
    public function index()
{
    $diasSemana = ['lunes','martes','miércoles','jueves','viernes','sábado','domingo'];
    $hoy = now()->locale('es')->dayName; // nombre del día en español

    $clients = Client::with(['shippingRoute', 'priceList', 'ultimaOrden'])
    ->orderBy('nombre')
    ->get()
    ->map(function ($client) {
        $ultimaOrden  = $client->ultimaOrden?->created_at;
        $diasPedido   = $client->dias_pedido ?? [];
        $periodicidad = $client->pedido_periodicidad ?? 7;

        $diasSinPedido = $ultimaOrden
            ? (int) $ultimaOrden->diffInDays(now())
            : null;

        $alerta = 0;
        if ($diasSinPedido !== null && count($diasPedido) > 0) {
            if ($diasSinPedido > $periodicidad * 2)        $alerta = 3;
            elseif ($diasSinPedido > $periodicidad * 1.5)  $alerta = 2;
            elseif ($diasSinPedido > $periodicidad)        $alerta = 1;
        }

        $client->dias_sin_pedido = $diasSinPedido;
        $client->alerta_nivel    = $alerta;
        return $client;
    });

    return view('admin.clients.index', compact('clients'));
}

    public function create()
    {
        return view('admin.clients.create', $this->formData());
    }

    public function edit(Client $client)
    {
        return view('admin.clients.edit', array_merge(
            $this->formData(),
            compact('client')
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data = $this->normalize($data);

        $client = Client::create($data);

        session()->flash('swal', ['icon'=>'success','title'=>'¡Bien Hecho!','text'=>'Cliente creado exitosamente.']);
        return redirect()->route('admin.clients.edit', $client);
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate($this->rules($client->id));
        $data = $this->normalize($data);

        $client->update($data);

        session()->flash('swal', ['icon'=>'success','title'=>'¡Bien Hecho!','text'=>'Cliente actualizado exitosamente.']);
        return redirect()->route('admin.clients.edit', $client);
    }

    public function destroy(Client $client)
    {
        if (!$client->activo) {
            session()->flash('swal', ['icon'=>'info','title'=>'Ya estaba inactivo','text'=>'El cliente ya se encontraba desactivado.']);
            return redirect()->route('admin.clients.index');
        }
        $client->update(['activo' => false]);
        session()->flash('swal', ['icon'=>'success','title'=>'Cliente desactivado','text'=>'Se desactivó correctamente.']);
        return redirect()->route('admin.clients.index');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formData(): array
    {
        return [
            'routes'       => ShippingRoute::orderBy('nombre')->get(['id','nombre']),
            'paymentTypes' => PaymentType::orderBy('descripcion')->get(['id','descripcion']),
            'priceLists'   => PriceList::orderBy('nombre')->get(['id','nombre']),
        ];
    }

    private function rules(?int $id = null): array
    {
        return [
            'nombre'           => ['required','string','max:180'],
            'email'            => ['nullable','email','max:150', Rule::unique('clients','email')->ignore($id)],
            'telefono'         => ['nullable','string','max:50'],
            'direccion'        => ['nullable','string','max:255'],
            'activo'           => ['required','boolean'],
            'tipo_persona'     => ['required','in:PF,PM'],
            'rfc'              => ['nullable','string','max:13'],
            'razon_social'     => ['nullable','string','max:180'],
            'nombre_comercial' => ['nullable','string','max:180'],
            'regimen_fiscal'   => ['nullable','string','max:10'],
            'uso_cfdi_default' => ['nullable','string','max:5'],

            'shipping_route_id' => ['nullable','integer','exists:shipping_routes,id'],
            'payment_type_id'   => ['nullable','integer','exists:payment_types,id'],
            'price_list_id'     => ['nullable','integer','exists:price_lists,id'],
            'credito_limite'    => ['nullable','numeric','min:0'],
            'credito_dias'      => ['nullable','integer','min:0','max:365'],

            // Dirección fiscal
            'fiscal_calle'   => ['nullable','string','max:255'],
            'fiscal_numero'  => ['nullable','string','max:50'],
            'fiscal_colonia' => ['nullable','string','max:255'],
            'fiscal_ciudad'  => ['nullable','string','max:255'],
            'fiscal_estado'  => ['nullable','string','max:255'],
            'fiscal_cp'      => ['nullable','string','max:10'],

            // Dirección de entrega
            'entrega_igual_fiscal' => ['nullable','boolean'],
            'entrega_calle'   => ['nullable','string','max:255'],
            'entrega_numero'  => ['nullable','string','max:50'],
            'entrega_colonia' => ['nullable','string','max:255'],
            'entrega_ciudad'  => ['nullable','string','max:255'],
            'entrega_estado'  => ['nullable','string','max:255'],
            'entrega_cp'      => ['nullable','string','max:10'],

            // Pedido recurrente
            'dias_pedido'        => ['nullable','array'],
            'dias_pedido.*'      => ['in:lunes,martes,miercoles,jueves,viernes,sabado,domingo'],
            'pedido_periodicidad'=> ['nullable','integer','min:1','max:365'],
        ];
    }

    private function normalize(array $data): array
{
    $data['activo'] = (bool)($data['activo'] ?? true);

    $tp = strtoupper((string)($data['tipo_persona'] ?? 'PF'));
    if ($tp === 'F') $tp = 'PF';
    if ($tp === 'M') $tp = 'PM';
    $data['tipo_persona'] = $tp;

    $data['entrega_igual_fiscal'] = !empty($data['entrega_igual_fiscal']);

    if ($data['entrega_igual_fiscal']) {
        $data['entrega_calle']   = $data['fiscal_calle']   ?? null;
        $data['entrega_numero']  = $data['fiscal_numero']  ?? null;
        $data['entrega_colonia'] = $data['fiscal_colonia'] ?? null;
        $data['entrega_ciudad']  = $data['fiscal_ciudad']  ?? null;
        $data['entrega_estado']  = $data['fiscal_estado']  ?? null;
        $data['entrega_cp']      = $data['fiscal_cp']      ?? null;
    }

    $isCredit = false;
    if (!empty($data['payment_type_id'])) {
        $cols      = Schema::getColumnListing('payment_types');
        $labelCols = array_values(array_intersect(['nombre','name','descripcion','tipo','titulo'], $cols));
        $q = DB::table('payment_types')->where('id', $data['payment_type_id']);
        foreach ($labelCols as $c) { $q->addSelect($c); }
        $row = $q->first();
        $label = '';
        if ($row) {
            foreach ($labelCols as $c) {
                if (!empty($row->$c)) { $label = (string)$row->$c; break; }
            }
        }
        $isCredit = Str::contains(Str::lower($label), ['crédito','credito']);
    }

    $data['credito_limite'] = $isCredit ? (float)($data['credito_limite'] ?? 0) : 0.0;
    $data['credito_dias']   = $isCredit ? (int)($data['credito_dias']   ?? 0) : 0;

    foreach (['rfc','razon_social','nombre_comercial','regimen_fiscal','uso_cfdi_default'] as $k) {
        if (isset($data[$k]) && is_string($data[$k])) $data[$k] = trim($data[$k]);
    }

    // Pedido recurrente
    $data['dias_pedido']         = $data['dias_pedido'] ?? [];
    $data['pedido_periodicidad'] = (int)($data['pedido_periodicidad'] ?? 7);

    return $data;
}

    public function pricesData(Request $request, Client $client)
{
    $search  = $request->get('search', '');
    $perPage = (int) $request->get('per_page', 15);
    $page    = (int) $request->get('page', 1);

    $q = \App\Models\Product::select('id','sku','nombre','precio_base')
        ->orderBy('nombre')
        ->when($search, fn($q) => $q->where(fn($q) =>
            $q->where('nombre','like',"%$search%")
              ->orWhere('sku','like',"%$search%")
        ));

    $total    = $q->count();
    $products = $q->skip(($page - 1) * $perPage)->take($perPage)->get();

    $overrides = \Illuminate\Support\Facades\DB::table('client_price_overrides')
        ->where('client_id', $client->id)
        ->pluck('precio','product_id');

    $listPrices = [];
    if ($client->price_list_id) {
        $listPrices = \Illuminate\Support\Facades\DB::table('price_list_items')
            ->where('price_list_id', $client->price_list_id)
            ->pluck('precio','product_id');
    }

    return response()->json([
        'products'   => $products,
        'overrides'  => $overrides,
        'listPrices' => $listPrices,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $perPage,
        'last_page'  => (int) ceil($total / $perPage),
    ]);
}

public function pricesSave(Request $request, Client $client)
{
    $prices = $request->input('prices', []);

    \Illuminate\Support\Facades\DB::transaction(function () use ($client, $prices) {
        foreach ($prices as $productId => $precio) {
            $precio = is_numeric($precio) ? (float) $precio : 0.0;
            \Illuminate\Support\Facades\DB::table('client_price_overrides')->updateOrInsert(
                ['client_id' => $client->id, 'product_id' => (int)$productId],
                ['precio' => $precio, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    });

    return response()->json(['ok' => true, 'message' => 'Precios guardados correctamente.']);
}
}