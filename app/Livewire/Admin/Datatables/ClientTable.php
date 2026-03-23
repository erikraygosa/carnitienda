<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\Client;
use Livewire\Component;
use Livewire\WithPagination;

class ClientTable extends Component
{
    use WithPagination;

    public string $search   = '';
    public string $activo   = '';
    public string $sortBy   = 'id';
    public string $sortDir  = 'desc';
    public int    $perPage  = 15;

    public function updatingSearch(): void  { $this->resetPage(); }
    public function updatingActivo(): void  { $this->resetPage(); }
    public function updatingPerPage(): void { $this->resetPage(); }

    public function sort(string $col): void
    {
        if ($this->sortBy === $col) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $col;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function render()
    {
        $q = Client::with(['shippingRoute','paymentType','priceList'])
            ->when($this->search, function ($q) {
                $t = '%'.$this->search.'%';
                $q->where(fn($q) =>
                    $q->where('nombre','like',$t)
                      ->orWhere('email','like',$t)
                      ->orWhere('telefono','like',$t)
                );
            })
            ->when($this->activo !== '', fn($q) => $q->where('activo', $this->activo))
            ->orderBy($this->sortBy, $this->sortDir);

        return view('livewire.admin.datatables.client-table', [
            'clients' => $q->paginate($this->perPage),
        ]);
    }
}