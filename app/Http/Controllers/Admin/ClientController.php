<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Schema;


class ClientController extends Controller
{
    public function index()  { return view('admin.clients.index'); }
    public function create() { return view('admin.clients.create'); }
  
    public function edit(Client $client) { return view('admin.clients.edit', compact('client')); }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $this->validateBusiness($data);
        $data = $this->normalize($data);

        $client = Client::create($data);

        session()->flash('swal', ['icon'=>'success','title'=>'¡Bien Hecho!','text'=>'Cliente creado exitosamente.']);
        return redirect()->route('admin.clients.edit', $client);
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate($this->rules($client->id));
        $this->validateBusiness($data);
        $data = $this->normalize($data);

        $client->update($data);

        session()->flash('swal', ['icon'=>'success','title'=>'¡Bien Hecho!','text'=>'Cliente actualizado exitosamente.']);
        return redirect()->route('admin.clients.edit', $client);
    }

    public function destroy(Client $client)
    {
        if (! $client->activo) {
            session()->flash('swal', ['icon'=>'info','title'=>'Ya estaba inactivo','text'=>'El cliente ya se encontraba desactivado.']);
            return redirect()->route('admin.clients.index');
        }

        $client->update(['activo' => false]);

        session()->flash('swal', ['icon'=>'success','title'=>'Cliente desactivado','text'=>'Se desactivó correctamente.']);
        return redirect()->route('admin.clients.index');
    }

    /** --------- Helpers --------- */

   private function rules(?int $id = null): array
{
    return [
        'nombre'            => ['required','string','max:180'],
        'email'             => ['nullable','email','max:150', Rule::unique('clients','email')->ignore($id)],
        'telefono'          => ['nullable','string','max:50'],
        'direccion'         => ['nullable','string','max:255'],
        'activo'            => ['required','boolean'],

        // Usa PF | PM (coincide con la migración)
        'tipo_persona'      => ['required','in:PF,PM'],

        'rfc'               => ['nullable','string','max:13'],
        'razon_social'      => ['nullable','string','max:180'],
        'nombre_comercial'  => ['nullable','string','max:180'],
        'regimen_fiscal'    => ['nullable','string','max:10'],
        'uso_cfdi_default'  => ['nullable','string','max:5'],

        'shipping_route_id' => ['nullable','integer','exists:shipping_routes,id'],
        'payment_type_id'   => ['nullable','integer','exists:payment_types,id'],

        // <-- AHORA OPCIONAL
        'price_list_id'     => ['nullable','integer','exists:price_lists,id'],

        'credito_limite'    => ['nullable','numeric','min:0'],
        'credito_dias'      => ['nullable','integer','min:0','max:365'],
    ];
}


    private function validateBusiness(array $data): void
    {
        // Mantener opcionales fiscales; aquí podrías endurecer para MORAL si un día lo decides.
    }

    /**
     * Normaliza campos (crédito y banderas).
     * - Si no hay tipo de pago de crédito, forzamos crédito_limite/dias a 0.
     * - Si vienen nulos, a 0.
     */
private function normalize(array $data): array
{
    $data['activo'] = (bool)($data['activo'] ?? true);

    // Si por cualquier razón llega F/M, mapea a PF/PM
    if (!empty($data['tipo_persona'])) {
        $tp = strtoupper((string)$data['tipo_persona']);
        if ($tp === 'F') $tp = 'PF';
        if ($tp === 'M') $tp = 'PM';
        $data['tipo_persona'] = $tp;
    } else {
        $data['tipo_persona'] = 'PF';
    }

    // Detectar si el tipo de pago “parece” crédito para habilitar crédito
    $isCredit = false;
    if (!empty($data['payment_type_id'])) {
        $cols = Schema::getColumnListing('payment_types');
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
        $isCredit = \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower($label), ['crédito','credito']);
    }

    $lim  = isset($data['credito_limite']) ? (float)$data['credito_limite'] : 0.0;
    $dias = isset($data['credito_dias'])   ? (int)$data['credito_dias']   : 0;

    if (!$isCredit) { $lim = 0.0; $dias = 0; }

    $data['credito_limite'] = $lim;
    $data['credito_dias']   = $dias;

    foreach (['rfc','razon_social','nombre_comercial','regimen_fiscal','uso_cfdi_default'] as $k) {
        if (isset($data[$k]) && is_string($data[$k])) $data[$k] = trim($data[$k]);
    }

    return $data;
}

}
