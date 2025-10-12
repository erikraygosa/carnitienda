<?php

namespace App\Livewire\Admin\Products;

use App\Models\Product;
use App\Models\ProductSubproductRule;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SubproductRules extends Component
{
    public Product $product;

    // Form
    public ?int $sub_product_id = null;
    public ?float $rendimiento_pct = null;  // % de salida
    public ?float $merma_porcent = null;    // % adicional (opcional)

    // Edición
    public ?int $editingId = null;

    // Opciones
    public array $subproductOptions = [];

    public function mount(Product $product): void
    {
        $this->product = $product;

        // Listado de posibles subproductos (activos, marcados como subproducto y distintos del padre)
        $this->subproductOptions = Product::query()
            ->where('es_subproducto', 1)
            ->where('id', '!=', $this->product->id)
            ->when(true, fn($q) => $q->where('activo', 1)) // si deseas permitir inactivos, quita esto
            ->orderBy('nombre')
            ->get(['id','nombre'])
            ->map(fn($p) => ['id' => $p->id, 'name' => $p->nombre])
            ->toArray();
    }

    public function rules(): array
    {
        return [
            'sub_product_id' => [
                'required',
                'integer',
                Rule::exists('products','id'),
                // Evita duplicados para este padre
                Rule::unique('product_subproduct_rules','sub_product_id')
                    ->where('main_product_id', $this->product->id)
                    ->ignore($this->editingId),
            ],
            'rendimiento_pct' => ['required','numeric','min:0.001','max:100'],
            'merma_porcent'   => ['nullable','numeric','min:0','max:100'],
        ];
    }

    public function create(): void
    {
        $this->validate();

        ProductSubproductRule::create([
            'main_product_id' => $this->product->id,
            'sub_product_id'  => $this->sub_product_id,
            'rendimiento_pct' => $this->rendimiento_pct,
            'merma_porcent'   => $this->merma_porcent ?? 0,
            'activo'          => 1,
        ]);

        $this->resetForm();
        $this->dispatch('notify', icon:'success', title:'Regla creada', text:'Se agregó el subproducto correctamente.');
    }

    public function edit(int $id): void
    {
        $rule = ProductSubproductRule::where('main_product_id', $this->product->id)->findOrFail($id);

        $this->editingId      = $rule->id;
        $this->sub_product_id = $rule->sub_product_id;
        $this->rendimiento_pct= (float) $rule->rendimiento_pct;
        $this->merma_porcent  = (float) ($rule->merma_porcent ?? 0);
    }

    public function update(): void
    {
        $this->validate();

        $rule = ProductSubproductRule::where('main_product_id', $this->product->id)->findOrFail($this->editingId);

        $rule->update([
            'sub_product_id'  => $this->sub_product_id,
            'rendimiento_pct' => $this->rendimiento_pct,
            'merma_porcent'   => $this->merma_porcent ?? 0,
        ]);

        $this->resetForm();
        $this->dispatch('notify', icon:'success', title:'Regla actualizada', text:'Se guardaron los cambios.');
    }

    public function delete(int $id): void
    {
        $rule = ProductSubproductRule::where('main_product_id', $this->product->id)->findOrFail($id);
        $rule->delete();

        $this->resetForm();
        $this->dispatch('notify', icon:'success', title:'Regla eliminada', text:'Se eliminó el subproducto.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->sub_product_id = null;
        $this->rendimiento_pct = null;
        $this->merma_porcent = null;
        // Refrescar opciones si quieres filtrar dinámicamente
    }

    public function render()
    {
        $rules = ProductSubproductRule::with('subproduct')
            ->where('main_product_id', $this->product->id)
            ->orderBy('id','desc')
            ->get();

        return view('livewire.admin.products.subproduct-rules', compact('rules'));
    }
}
