<?php

namespace App\Livewire\Admin\Clients;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class ClientPriceEditor extends Component
{
    use WithPagination;

    public bool $open = false;
    public int $clientId;
    public ?int $priceListId = null;

    public string $search = '';
    public int $perPage = 10;

    /** precios tecleados en UI: [product_id => precio] */
    public array $inputs = [];

    /** overrides ya guardados en BD: [product_id => precio] */
    public array $storedOverrides = [];

    protected $listeners = [
        // para abrir modal desde la vista de cliente
        'openClientPriceEditor' => 'openForClient',
    ];

    public function mount(int $clientId): void
    {
        $this->clientId = $clientId;

        // Traer lista de precios del cliente
        $this->priceListId = (int) DB::table('clients')->where('id', $clientId)->value('price_list_id');

        // Traer overrides guardados
        $this->storedOverrides = DB::table('client_price_overrides')
            ->where('client_id', $this->clientId)
            ->pluck('precio', 'product_id')
            ->map(fn($v) => (float) $v)
            ->toArray();

        // Cargar inputs iniciales con lo ya guardado
        $this->inputs = $this->storedOverrides;
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingPerPage(): void { $this->resetPage(); }

    public function openForClient(int $clientId): void
    {
        if ($this->clientId !== $clientId) {
            $this->mount($clientId);
        }
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function setZeroForEmpty(): void
    {
        // Si algún producto visible no tiene precio capturado, poner 0
        foreach ($this->visibleProductIds() as $pid) {
            if (!array_key_exists($pid, $this->inputs) || $this->inputs[$pid] === '' || $this->inputs[$pid] === null) {
                $this->inputs[$pid] = 0;
            }
        }
        $this->dispatch('notify', icon:'success', title:'Listo', text:'Campos vacíos visibles puestos en 0.');
    }

    public function save(): void
    {
        // Guardar todos los inputs presentes (pueden venir de varias páginas si el usuario navega)
        // Reglas del negocio: si no hay valor, guardamos 0 (según tu requerimiento)
        DB::transaction(function () {
            foreach ($this->inputs as $productId => $precio) {
                $precio = is_numeric($precio) ? (float) $precio : 0.0;

                DB::table('client_price_overrides')->updateOrInsert(
                    ['client_id' => $this->clientId, 'product_id' => (int)$productId],
                    ['precio' => $precio, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        // refrescar cache local
        $this->storedOverrides = DB::table('client_price_overrides')
            ->where('client_id', $this->clientId)
            ->pluck('precio', 'product_id')
            ->map(fn($v) => (float) $v)
            ->toArray();

        $this->dispatch('notify', icon:'success', title:'Precios guardados', text:'Se actualizaron los precios personalizados.');
    }

    /**
     * Productos paginados
     */
    public function products()
    {
        $q = DB::table('products')
            ->select('id', 'sku', 'nombre', 'precio_base')
            ->orderBy('nombre');

        if ($this->search) {
            $t = '%'.$this->search.'%';
            $q->where(function ($qq) use ($t) {
                $qq->where('nombre','like',$t)
                   ->orWhere('sku','like',$t);
            });
        }

        return $q->paginate($this->perPage);
    }

    /**
     * Devuelve ids de los productos visibles en la página actual (para utilidades)
     */
    protected function visibleProductIds(): array
    {
        return $this->products()->getCollection()->pluck('id')->all();
    }

    /**
     * Precio de lista del cliente (si existe price_list_id)
     */
    public function listPrice(int $productId): ?float
    {
        if (!$this->priceListId) return null;

        $v = DB::table('price_list_items')
            ->where('price_list_id', $this->priceListId)
            ->where('product_id', $productId)
            ->value('precio');

        return $v !== null ? (float) $v : null;
    }

    /**
     * Precio efectivo: override si existe (input -> stored), si no, lista, si no, base
     */
    public function effectivePrice(float $base, ?float $list, int $productId): float
    {
        $val = $this->inputs[$productId] ?? $this->storedOverrides[$productId] ?? null;
        if ($val !== null && $val !== '') {
            return (float) $val;
        }
        if ($list !== null) return (float) $list;
        return (float) $base;
    }

    public function render()
    {
        return view('livewire.admin.clients.client-price-editor', [
            'rows' => $this->products(),
        ]);
    }
}
