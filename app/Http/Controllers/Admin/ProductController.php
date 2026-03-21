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
        $payload = $request->merge([
            'es_compuesto'      => $request->boolean('es_compuesto'),
            'es_subproducto'    => $request->boolean('es_subproducto'),
            'maneja_inventario' => $request->boolean('maneja_inventario'),
            'activo'            => $request->boolean('activo'),
        ])->all();

        $data = validator($payload, $this->rules())->validate();

        if (($data['es_compuesto'] ?? false) && ($data['es_subproducto'] ?? false)) {
            return back()
                ->withErrors(['es_compuesto' => 'Un producto no puede ser compuesto y subproducto al mismo tiempo.'])
                ->withInput();
        }

        $data = $this->normalize($data);
        $data = $this->syncSatTasa($data);

        $product = Product::create($data);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('swal', ['icon' => 'success', 'title' => '¡Bien Hecho!', 'text' => 'Producto creado exitosamente.']);
    }

    /** Mostrar */
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
        $payload = $request->merge([
            'es_compuesto'      => $request->boolean('es_compuesto'),
            'es_subproducto'    => $request->boolean('es_subproducto'),
            'maneja_inventario' => $request->boolean('maneja_inventario'),
            'activo'            => $request->boolean('activo'),
        ])->all();

        $data = validator($payload, $this->rules($product->id))->validate();

        if (($data['es_compuesto'] ?? false) && ($data['es_subproducto'] ?? false)) {
            return back()
                ->withErrors(['es_compuesto' => 'Un producto no puede ser compuesto y subproducto al mismo tiempo.'])
                ->withInput();
        }

        $data = $this->normalize($data);
        $data = $this->syncSatTasa($data);

        $product->update($data);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('swal', ['icon' => 'success', 'title' => '¡Bien Hecho!', 'text' => 'Producto actualizado exitosamente.']);
    }

    /** Desactivar (no borrar) */
    public function destroy(Product $product)
    {
        $tieneDependencias =
            (method_exists($product, 'priceListItems') && $product->priceListItems()->exists()) ||
            (method_exists($product, 'saleItems')      && $product->saleItems()->exists()) ||
            (method_exists($product, 'quoteItems')     && $product->quoteItems()->exists());

        if (!$product->activo) {
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

    /** Despiece */
    public function despiece(Request $request, Product $product, \App\Services\InventoryService $inventory)
    {
        $data = $request->validate([
            'cantidad'     => ['required', 'numeric', 'min:0.001'],
            'warehouse_id' => ['nullable', 'integer'],
            'nota'         => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $inventory->despiece(
                $product,
                (float) $data['cantidad'],
                $data['warehouse_id'] ?? null,
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

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Reglas de validación compartidas entre store y update.
     * Cuando $productId es null = store (unique sin ignore).
     */
    private function rules(?int $productId = null): array
    {
        $unique = fn(string $col) => $productId
            ? Rule::unique('products', $col)->ignore($productId)
            : Rule::unique('products', $col);

        return [
            // ── Base ─────────────────────────────────────────────────────────
            'nombre'            => ['required', 'string', 'max:180', $unique('nombre')],
            'sku'               => ['nullable', 'string', 'max:60',  $unique('sku')],
            'barcode'           => ['nullable', 'string', 'max:100', $unique('barcode')],
            'unidad'            => ['required', 'string', 'max:20'],
            'category_id'       => ['required', 'integer', 'exists:categories,id'],
            'es_compuesto'      => ['required', 'boolean'],
            'es_subproducto'    => ['required', 'boolean'],
            'precio_base'       => ['required', 'numeric', 'min:0'],
            'costo_promedio'    => ['required', 'numeric', 'min:0'],
            'tasa_iva'          => ['required', 'numeric', 'min:0', 'max:100'],
            'stock_min'         => ['required', 'numeric', 'min:0'],
            'maneja_inventario' => ['required', 'boolean'],
            'activo'            => ['required', 'boolean'],
            'notas'             => ['nullable', 'string'],

            // ── SAT / CFDI 4.0 ───────────────────────────────────────────────
            // c_ClaveProdServ: 8 dígitos numéricos. Ej: "50202306"
            'sat_clave_prod_serv'   => ['nullable', 'string', 'max:20'],

            // c_ClaveUnidad: 1-3 chars alfanuméricos. Ej: "KGM", "H87", "LTR"
            'sat_clave_unidad'      => ['nullable', 'string', 'max:10'],

            // c_ObjetoImp: 01, 02 o 03
            'sat_objeto_imp'        => ['nullable', 'in:01,02,03'],

            // c_TipoFactor
            'sat_tipo_factor'       => ['nullable', 'in:Tasa,Exento,Cuota'],

            // Tasa IVA en decimal (0.160000). Si viene vacío se calcula de tasa_iva.
            'sat_tasa_iva'          => ['nullable', 'numeric', 'min:0', 'max:1'],

            // Tasa IEPS en decimal
            'sat_tasa_ieps'         => ['nullable', 'numeric', 'min:0', 'max:1'],

            // NoIdentificacion
            'sat_no_identificacion' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** Normaliza strings del producto. */
    private function normalize(array $data): array
    {
        $data['nombre']  = trim($data['nombre']);
        $data['sku']     = isset($data['sku'])     && $data['sku']     ? trim($data['sku'])     : null;
        $data['barcode'] = isset($data['barcode']) && $data['barcode'] ? trim($data['barcode']) : null;

        // Claves SAT en mayúsculas y sin espacios
        if (!empty($data['sat_clave_prod_serv'])) {
            $data['sat_clave_prod_serv'] = strtoupper(trim($data['sat_clave_prod_serv']));
        }
        if (!empty($data['sat_clave_unidad'])) {
            $data['sat_clave_unidad'] = strtoupper(trim($data['sat_clave_unidad']));
        }

        return $data;
    }

    /**
     * Si sat_tasa_iva no viene del form (o viene vacío), la calcula
     * automáticamente de tasa_iva (porcentaje → decimal).
     * Si sat_tipo_factor = Exento, fuerza sat_tasa_iva = null.
     */
    private function syncSatTasa(array $data): array
    {
        $factor = $data['sat_tipo_factor'] ?? 'Tasa';

        if ($factor === 'Exento') {
            $data['sat_tasa_iva'] = null;
        } elseif (empty($data['sat_tasa_iva']) && isset($data['tasa_iva'])) {
            // Auto-calcular del porcentaje base
            $data['sat_tasa_iva'] = round((float) $data['tasa_iva'] / 100, 6);
        }

        // sat_objeto_imp por defecto si no viene
        if (empty($data['sat_objeto_imp'])) {
            $data['sat_objeto_imp'] = '02'; // sí objeto de impuesto
        }

        // sat_tipo_factor por defecto
        if (empty($data['sat_tipo_factor'])) {
            $data['sat_tipo_factor'] = 'Tasa';
        }

        return $data;
    }
}