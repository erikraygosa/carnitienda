<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverCashRegister;
use App\Services\DriverCashService;
use Illuminate\Http\Request;

class DriverCashRegisterController extends Controller
{
    public function __construct(private DriverCashService $cash) {}

    public function index()
    {
        return view('admin.driver-cash.index');
    }

    public function create()
    {
        // La tabla drivers tiene: id, nombre, telefono, licencia, activo, created_at, updated_at
        $drivers = Driver::orderBy('nombre')->get();

        return view('admin.driver-cash.create', compact('drivers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'driver_id'     => 'required|exists:drivers,id',
            'fecha'         => 'required|date',
            'saldo_inicial' => 'nullable|numeric|min:0',
            'notas'         => 'nullable|string',
        ]);

        $reg = $this->cash->getOrOpenRegister(
            $data['driver_id'],
            $data['fecha'],
            $data['saldo_inicial'] ?? 0
        );

        $reg->update(['notas' => $data['notas'] ?? null]);

        session()->flash('swal', [
            'icon'  => 'success',
            'title' => '¡Listo!',
            'text'  => 'Corte abierto.',
        ]);

        return redirect()->route('admin.driver-cash.show', $reg);
    }

    public function show(DriverCashRegister $register)
    {
        // Por si la vista muestra el chofer, cargamos también la relación driver
        $register->load(['movements', 'driver']);

        return view('admin.driver-cash.show', compact('register'));
    }

    public function abono(Request $request, DriverCashRegister $register)
    {
        $data = $request->validate([
            'monto'       => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string',
        ]);

        $this->cash->addMovement($register, 'ABONO', $data['monto'], $data['descripcion']);

        session()->flash('swal', [
            'icon'  => 'success',
            'title' => 'Abono registrado',
            'text'  => 'Se actualizó el saldo.',
        ]);

        return back();
    }

    public function close(Request $request, DriverCashRegister $register)
    {
        $requireZero = (bool) $request->boolean('require_zero', false);

        $this->cash->close($register, $requireZero);

        session()->flash('swal', [
            'icon'  => 'success',
            'title' => 'Corte cerrado',
            'text'  => 'Se cerró correctamente.',
        ]);

        return redirect()->route('admin.driver-cash.index');
    }
}
