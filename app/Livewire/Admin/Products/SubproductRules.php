<?php

namespace App\Livewire\Admin\Products;

use App\Models\Product;
use App\Models\ProductSubproductRule;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SubproductRules extends Component
{
    public Product $product;

    // Form (UI en %)
    public ?int $sub_product_id = null;
    public ?float $rendimiento_pct = null;  // % mostrado en UI
    public ?float $merma_porcent = null;    // % mostrado en UI

    // Edición
    public ?int $editingId = null;

    // Opciones de subproducto
    public array $subproductOptions = [];

    public function mount(Product $product): void
    {
        $this->product = $product;

        $this->subproductOptions = Product::query()
            ->where('es_subproducto', 1)
            ->where('id', '!=', $this->product->id)
            ->where('activo', 1)
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
                Rule::unique('product_subproduct_rules','sub_product_id')
                    ->where(fn($q) => $q->where('main_product_id', $this->product->id))
                    ->ignore($this->editingId),
            ],
            'rendimiento_pct' => ['required','numeric','min:0.001','max:100000'], // permite 85 ó 0.85
            'merma_porcent'   => ['nullable','numeric','min:0','max:100'],
        ];
    }

    /** Convierte % (UI) a factor (DB) con 6 decimales */
    private function pctToRatio(float $pct): float
    {
        $r = $pct;
        if ($r > 1) { $r = $r / 100.0; }     // 85 => 0.85
        return max(0.0, round($r, 6));
    }

    /** Convierte factor (DB) a % (UI) con 3 decimales */
    private function ratioToPct(float $ratio): float
    {
        return round($ratio * 100.0, 3);
    }

    public function create(): void
    {
        $this->validate();

        ProductSubproductRule::create([
            'main_product_id' => $this->product->id,
            'sub_product_id'  => (int) $this->sub_product_id,
            'ratio'           => $this->pctToRatio((float)$this->rendimiento_pct),
            'merma_porcent'   => round((float)($this->merma_porcent ?? 0), 4),
            'activo'          => 1,
        ]);

        $this->resetForm();
        $this->dispatch('notify', icon:'success', title:'Regla creada', text:'Se agregó el subproducto.');
    }

    public function edit(int $id): void
    {
        $rule = ProductSubproductRule::where('main_product_id', $this->product->id)->findOrFail($id);

        $this->editingId       = $rule->id;
        $this->sub_product_id  = (int) $rule->sub_product_id;
        $this->rendimiento_pct = $this->ratioToPct((float)($rule->ratio ?? 0));   // factor → %
        $this->merma_porcent   = (float) ($rule->merma_porcent ?? 0);
        $this->resetErrorBag();
    }

    public function update(): void
    {
        $this->validate();

        $rule = ProductSubproductRule::where('main_product_id', $this->product->id)->findOrFail($this->editingId);

        $rule->update([
            'sub_product_id'  => (int) $this->sub_product_id,
            'ratio'           => $this->pctToRatio((float)$this->rendimiento_pct),
            'merma_porcent'   => round((float)($this->merma_porcent ?? 0), 4),
            'activo'          => 1,
        ]);

        $this->resetForm();
        $this->dispatch('notify', icon:'success', title:'Regla actualizada', text:'Cambios guardados.');
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
