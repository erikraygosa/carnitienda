<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\InvoiceSeries;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeriesController extends Controller
{
    public function index()
    {
        $series    = InvoiceSeries::with('company')->orderBy('company_id')->orderBy('serie')->get();
        $companies = Company::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']);

        return view('superadmin.series.index', compact('series', 'companies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id'       => ['required', 'exists:companies,id'],
            'serie'            => ['required', 'string', 'max:10', 'alpha_num'],
            'tipo_comprobante' => ['required', Rule::in(['I', 'E', 'P', 'N'])],
            'folio_inicio'     => ['required', 'integer', 'min:1'],
            'descripcion'      => ['nullable', 'string', 'max:200'],
            'es_default'       => ['boolean'],
        ]);

        // Si es default, desmarcar los demás del mismo tipo y empresa
        if ($request->boolean('es_default')) {
            InvoiceSeries::where('company_id', $data['company_id'])
                ->where('tipo_comprobante', $data['tipo_comprobante'])
                ->update(['es_default' => false]);
        }

        InvoiceSeries::create([
            ...$data,
            'folio_actual' => $data['folio_inicio'] - 1,
            'es_default'   => $request->boolean('es_default'),
            'activa'       => true,
        ]);

        return back()->with('success', "Serie {$data['serie']} creada correctamente.");
    }

    public function update(Request $request, InvoiceSeries $serie)
    {
        $data = $request->validate([
            'descripcion' => ['nullable', 'string', 'max:200'],
            'activa'      => ['boolean'],
            'es_default'  => ['boolean'],
        ]);

        if ($request->boolean('es_default')) {
            InvoiceSeries::where('company_id', $serie->company_id)
                ->where('tipo_comprobante', $serie->tipo_comprobante)
                ->where('id', '!=', $serie->id)
                ->update(['es_default' => false]);
        }

        $serie->update($data);

        return back()->with('success', 'Serie actualizada.');
    }

    public function destroy(InvoiceSeries $serie)
    {
        if ($serie->folio_actual > $serie->folio_inicio) {
            return back()->with('error', 'No se puede eliminar una serie que ya tiene folios usados.');
        }

        $serie->delete();
        return back()->with('success', 'Serie eliminada.');
    }
}