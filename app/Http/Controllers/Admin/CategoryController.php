<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
   
    public function index()
    {
        return view('admin.categories.index');
    }

    
    public function create()
    {
        return view('admin.categories.create');
    }

    
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255|unique:categories,nombre',
            'descripcion' => 'nullable|string',
            
        ]);

        $category = Category::create($data);

        session()->flash('swal',[
            'icon' => 'success',
            'title' => 'Bien Hecno!',
            'text' => 'Categoría creada exitosamente.'
        ]);

        return redirect()->route('admin.categories.edit', $category);
    }

   
    public function edit(Category $category)
    {
        return view('admin.categories.edit', compact('category'));
    }

    
    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255|unique:categories,nombre,' . $category->id,
            'descripcion' => 'nullable|string',
            'activo' => 'required|boolean',
        ]);

        $category->update($data);

        session()->flash('swal',[
            'icon' => 'success',
            'title' => 'Bien Hecno!',
            'text' => 'Categoría actualizada exitosamente.'
        ]);

        return redirect()->route('admin.categories.edit', $category);
    }

    
    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            session()->flash('swal',[
                'icon' => 'error',
                'title' => 'Error!',
                'text' => 'No se puede eliminar la categoría porque tiene productos asociados.'
            ]);

            return redirect()->route('admin.categories.index');
        }

        $category->delete();

        session()->flash('swal',[
            'icon' => 'success',
            'title' => 'Bien Hecno!',
            'text' => 'Categoría eliminada exitosamente.'
        ]);

        return redirect()->route('admin.categories.index');
    }
}
