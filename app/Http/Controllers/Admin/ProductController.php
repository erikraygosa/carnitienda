<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /** Listado */
    public function index()
    {
        return view('admin.products.index');
    }

    /** Form creación */
    public function create()
    {
        return view('admin.products.create');
    }

    /** Guardar */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'            => ['required','string','max:180','unique:products,nombre'],
            'sku'               => ['nullable','string','max:60','unique:products,sku'],
            'barcode'           => ['nullable','string','max:100','unique:products,barcode'],
            'unidad'            => ['required','string','max:20'],
            'category_id'       => ['required','integer','exists:categories,id'],
            'es_compuesto'      => ['required','boolean'],
            'es_subproducto'    => ['required','boolean'],
            'precio_base'       => ['required','numeric','min:0'],
            'costo_promedio'    => ['required','numeric','min:0'],
            'tasa_iva'          => ['required','numeric','min:0','max:100'],
            'stock_min'         => ['required','numeric','min:0'],
            'maneja_inventario' => ['required','boolean'],
            'activo'            => ['required','boolean'],
            'notas'             => ['nullable','string'],
        ]);

        // Regla de negocio: no ambas banderas a la vez
        if (($data['es_compuesto'] ?? false) && ($data['es_subproducto'] ?? false)) {
            return back()
                ->withErrors(['es_compuesto' => 'Un producto no puede ser compuesto y subproducto al mismo tiempo.'])
                ->withInput();
        }

        $product = Product::create($data);

        session()->flash('swal', [
            'icon'  => 'success',
            'title' => '¡Bien Hecho!',
            'text'  => 'Producto creado exitosamente.',
        ]);

        return redirect()->route('admin.products.edit', $product);
    }

    /** Mostrar (opcional) */
    public function show(Product $product)
    {
        return view('admin.products.show', compact('product'));
    }

    /** Form edición */
    public function edit(Product $product)
    {
        return view('admin.products.edit', compact('product'));
    }

    /** Actualizar */
    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'nombre'            => ['required','string','max:180', Rule::unique('products','nombre')->ignore($product->id)],
            'sku'               => ['nullable','string','max:60',  Rule::unique('products','sku')->ignore($product->id)],
            'barcode'           => ['nullable','string','max:100', Rule::unique('products','barcode')->ignore($product->id)],
            'unidad'            => ['required','string','max:20'],
            'category_id'       => ['required','integer','exists:categories,id'],
            'es_compuesto'      => ['required','boolean'],
            'es_subproducto'    => ['required','boolean'],
            'precio_base'       => ['required','numeric','min:0'],
            'costo_promedio'    => ['required','numeric','min:0'],
            'tasa_iva'          => ['required','numeric','min:0','max:100'],
            'stock_min'         => ['required','numeric','min:0'],
            'maneja_inventario' => ['required','boolean'],
            'activo'            => ['required','boolean'],
            'notas'             => ['nullable','string'],
        ]);

        if (($data['es_compuesto'] ?? false) && ($data['es_subproducto'] ?? false)) {
            return back()
                ->withErrors(['es_compuesto' => 'Un producto no puede ser compuesto y subproducto al mismo tiempo.'])
                ->withInput();
        }

        $product->update($data);

        session()->flash('swal', [
            'icon'  => 'success',
            'title' => '¡Bien Hecho!',
            'text'  => 'Producto actualizado exitosamente.',
        ]);

        return redirect()->route('admin.products.edit', $product);
    }

    /** Desactivar (no borrar) */
    public function destroy(Product $product)
    {
        $tieneDependencias =
            (method_exists($product, 'priceListItems') && $product->priceListItems()->exists()) ||
            (method_exists($product, 'saleItems')      && $product->saleItems()->exists()) ||
            (method_exists($product, 'quoteItems')     && $product->quoteItems()->exists());

        if (! $product->activo) {
            session()->flash('swal', [
                'icon'  => 'info',
                'title' => 'Ya estaba inactivo',
                'text'  => 'El producto ya se encontraba desactivado.',
            ]);
            return redirect()->route('admin.products.index');
        }

        $product->update(['activo' => false]);

        session()->flash('swal', [
            'icon'  => 'success',
            'title' => 'Producto desactivado',
            'text'  => $tieneDependencias
                ? 'Se desactivó porque tiene registros relacionados (listas de precios, ventas o cotizaciones).'
                : 'Se desactivó correctamente.',
        ]);

        return redirect()->route('admin.products.index');
    }

    /** Despiece: usa almacén principal por defecto */
    public function despiece(Request $request, Product $product, \App\Services\InventoryService $inventory)
    {
        $data = $request->validate([
            'cantidad'     => ['required','numeric','min:0.001'],
            'warehouse_id' => ['nullable','integer'],
            'nota'         => ['nullable','string','max:255'],
        ]);

        try {
            $inventory->despiece(
                $product,
                (float) $data['cantidad'],
                $data['warehouse_id'] ?? null, // si no viene, usa principal
                $data['nota'] ?? null,
                auth()->id()
            );

            session()->flash('swal', [
                'icon'  => 'success',
                'title' => 'Despiece realizado',
                'text'  => "Se procesó {$data['cantidad']} {$product->unidad} de {$product->nombre} en el almacén principal.",
            ]);
        } catch (\Throwable $e) {
            session()->flash('swal', [
                'icon'  => 'error',
                'title' => 'Error',
                'text'  => $e->getMessage(),
            ]);
        }

        return back();
    }
}
