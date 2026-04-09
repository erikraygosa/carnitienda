<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PaymentType;
use App\Services\ArService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SalesOrder;
use App\Models\ArMovement;
use App\Models\ArPayment;

class ArPaymentsController extends Controller
{
    public function __construct(private ArService $ar) {}

    public function create()
    {
        $clients     = Client::where('activo', 1)->orderBy('nombre')->get();
        $types       = PaymentType::orderBy('descripcion')->get();
        $preClientId = request('client_id');

        $notasPendientes = collect();
        if ($preClientId) {
            $notasPendientes = SalesOrder::where('client_id', $preClientId)
                ->where('payment_method', 'CREDITO')
                ->whereIn('status', ['ENTREGADO'])
                ->whereNull('cobrado_at')
                ->where(function($q) {
                    $q->whereNull('saldo_pendiente')
                      ->orWhere('saldo_pendiente', '>', 0);
                })
                ->orderBy('fecha')
                ->get(['id','folio','fecha','total','saldo_pendiente']);
        }

        return view('admin.ar.payments.create', compact('clients', 'types', 'preClientId', 'notasPendientes'));
    }

    public function notas(Request $request)
    {
        $clientId = $request->query('client_id');

        $orders = SalesOrder::where('client_id', $clientId)
            ->where('payment_method', 'CREDITO')
            ->whereIn('status', ['ENTREGADO'])
            ->whereNull('cobrado_at')
            ->where(function($q) {
                $q->whereNull('saldo_pendiente')
                  ->orWhere('saldo_pendiente', '>', 0);
            })
            ->orderBy('fecha')
            ->get(['id','folio','fecha','total','saldo_pendiente'])
            ->map(fn($o) => [
                'id'              => $o->id,
                'folio'           => $o->folio,
                'fecha'           => \Carbon\Carbon::parse($o->fecha)->format('d/m/Y'),
                'total'           => (float) $o->total,
                'saldo_pendiente' => ($o->saldo_pendiente !== null && (float)$o->saldo_pendiente > 0)
                    ? (float) $o->saldo_pendiente
                    : (float) $o->total,
            ]);

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'       => 'required|exists:clients,id',
            'fecha'           => 'required|date',
            'amount'          => 'required|numeric|min:0.01',
            'payment_type_id' => 'required|exists:payment_types,id',
            'reference'       => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
            'order_ids'       => 'nullable|array',
            'order_ids.*'     => 'integer|exists:sales_orders,id',
        ]);

        DB::transaction(function () use ($data) {
            $mov = ArMovement::create([
                'client_id'   => $data['client_id'],
                'fecha'       => $data['fecha'],
                'tipo'        => 'ABONO',
                'monto'       => $data['amount'],
                'descripcion' => 'Cobro' . (!empty($data['notes']) ? ': '.$data['notes'] : ''),
                'created_by'  => auth()->id(),
            ]);

            $payment = ArPayment::create([
                'fecha'           => $data['fecha'],
                'payment_type_id' => $data['payment_type_id'],
                'monto'           => $data['amount'],
                'referencia'      => $data['reference'] ?? null,
                'nota'            => $data['notes'] ?? null,
                'recibido_por'    => auth()->id(),
                'order_ids'       => $data['order_ids'] ?? [],
            ]);

            $mov->source_type = ArPayment::class;
            $mov->source_id   = $payment->id;
            $mov->save();

            if (!empty($data['order_ids'])) {
                $restante = (float) $data['amount'];

                $ordenes = SalesOrder::whereIn('id', $data['order_ids'])
                    ->orderBy('fecha')
                    ->get();

                foreach ($ordenes as $orden) {
                    if ($restante <= 0) break;

                    $saldo = ($orden->saldo_pendiente !== null && (float)$orden->saldo_pendiente > 0)
                        ? (float) $orden->saldo_pendiente
                        : (float) $orden->total;

                    $abono      = min($restante, $saldo);
                    $nuevoSaldo = round($saldo - $abono, 2);

                    $updateData = ['saldo_pendiente' => $nuevoSaldo];

                    if ($nuevoSaldo <= 0) {
                        $updateData['cobrado_at'] = now();
                    }

                    $orden->update($updateData);
                    $restante = round($restante - $abono, 2);
                }
            }
        });

        session()->flash('swal', ['icon'=>'success','title'=>'Cobro registrado','text'=>'El pago se aplicó correctamente.']);
        return redirect()->route('admin.ar.index');
    }
}