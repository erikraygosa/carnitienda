<?php

namespace App\Livewire\Admin\Datatables;

use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\TextFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
use App\Models\Category;

class CategoryTable extends DataTableComponent
{
    protected $model = Category::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
        $this->setDefaultSort('id', 'desc');
    }

    public function filters(): array
    {
        return [
            TextFilter::make('Nombre', 'nombre')
                ->filter(function ($query, $value) {
                    $query->where('nombre', 'like', "%{$value}%");
                }),

            TextFilter::make('Descripción', 'descripcion')
                ->filter(function ($query, $value) {
                    $query->where('descripcion', 'like', "%{$value}%");
                }),

            SelectFilter::make('Estatus', 'activo')
                ->options([
                    ''  => 'Todos',
                    '1' => 'Activo',
                    '0' => 'Inactivo',
                ])
                ->filter(function ($query, $value) {
                    if ($value !== '') {
                        $query->where('activo', $value);
                    }
                }),
        ];
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->searchable()
                ->sortable(),

            Column::make('Nombre', 'nombre')
                ->searchable()
                ->sortable(),

            Column::make('Descripción', 'descripcion')
                ->searchable()
                ->sortable(),

            Column::make('Estatus', 'activo')
                ->searchable()
                ->format(fn ($value) => $value
                    ? '<span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">Activo</span>'
                    : '<span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">Inactivo</span>'
                )
                ->html()
                ->sortable(),

            Column::make('Acciones')
                ->label(function ($row) {
                    return view('admin.categories.actions', ['category' => $row]);
                }),
        ];
    }
}