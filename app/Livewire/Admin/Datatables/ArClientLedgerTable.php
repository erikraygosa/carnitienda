<?php

namespace App\Livewire\Admin\Datatables;

use App\Models\ArMovement;
use App\Models\ArPayment;
use App\Models\PaymentType;
use App\Services\DocumentLogService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class ArClientLedgerTable extends Component
{
    use WithPagination;

    public int    $clientId;
    public string $search   = '';
    public string $tipo     = '';  // '' | CARGO | ABONO
    public int    $perPage  = 20;

    // Edición inline de un cobro (ar_payments) — solo forma de pago, fecha,
    // referencia y nota; el monto no se toca aquí porque ya quedó aplicado
    // (FIFO) a una o más notas y recalcularlo requeriría deshacer/rehacer
    // esa aplicación.
    public ?int $editingPaymentId  = null;
    public string $editFecha       = '';
    public ?int $editPaymentTypeId = null;
    public string $editReferencia  = '';
    public string $editNota        = '';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingTipo(): void   { $this->resetPage(); }

    public function editStart(int $paymentId): void
    {
        abort_unless(auth()->user()?->can('registrar cobros'), 403);

        $payment = ArPayment::findOrFail($paymentId);

        $this->editingPaymentId  = $payment->id;
        $this->editFecha         = \Carbon\Carbon::parse($payment->fecha)->format('Y-m-d');
        $this->editPaymentTypeId = $payment->payment_type_id;
        $this->editReferencia    = $payment->referencia ?? '';
        $this->editNota          = $payment->nota ?? '';
    }

    public function editCancel(): void
    {
        $this->reset(['editingPaymentId', 'editFecha', 'editPaymentTypeId', 'editReferencia', 'editNota']);
    }

    public function editSave(): void
    {
        abort_unless(auth()->user()?->can('registrar cobros'), 403);

        $this->validate([
            'editFecha'         => 'required|date',
            'editPaymentTypeId' => 'required|exists:payment_types,id',
            'editReferencia'    => 'nullable|string|max:255',
            'editNota'          => 'nullable|string|max:1000',
        ]);

        $payment = ArPayment::findOrFail($this->editingPaymentId);

        $before = [
            'fecha'           => \Carbon\Carbon::parse($payment->fecha)->format('Y-m-d'),
            'payment_type_id' => $payment->payment_type_id,
            'referencia'      => $payment->referencia,
            'nota'            => $payment->nota,
        ];
        $after = [
            'fecha'           => $this->editFecha,
            'payment_type_id' => $this->editPaymentTypeId,
            'referencia'      => $this->editReferencia ?: null,
            'nota'            => $this->editNota ?: null,
        ];

        $changes = [];
        foreach ($before as $campo => $valorAntes) {
            if ((string) $valorAntes !== (string) $after[$campo]) {
                $changes[$campo] = ['old' => $valorAntes, 'new' => $after[$campo]];
            }
        }

        if ($changes) {
            $payment->update($after);

            // El movimiento ABONO en ar_movements debe conservar la misma
            // fecha que su pago para no desalinear el estado de cuenta.
            ArMovement::where('source_type', ArPayment::class)
                ->where('source_id', $payment->id)
                ->update(['fecha' => $this->editFecha]);

            app(DocumentLogService::class)->log(
                $payment,
                'EDITADO',
                null, null, null,
                'Cobro #' . $payment->id . ' editado desde el estado de cuenta del cliente.',
                $changes,
            );

            session()->flash('swal', ['icon' => 'success', 'title' => 'Cobro actualizado', 'text' => 'Los cambios se guardaron correctamente.']);
        }

        $this->editCancel();
    }

    public function render()
    {
        // Saldo acumulado usando window function (MySQL 8+)
        // Calculamos el saldo corrido desde el más antiguo al más nuevo.
        // También traemos, vía subconsultas correlacionadas, cómo se pagó
        // cada ABONO (forma de pago / referencia / nota del ar_payments
        // enlazado) para mostrarlo y poder editarlo en la tabla.
        $arPaymentClass = addslashes(ArPayment::class);

        $subquery = ArMovement::where('client_id', $this->clientId)
            ->selectRaw("
                id, client_id, fecha, tipo, monto, descripcion,
                source_type, source_id, created_at,
                SUM(CASE WHEN tipo = 'CARGO' THEN monto ELSE -monto END)
                    OVER (ORDER BY fecha ASC, id ASC) AS saldo_acumulado,
                (SELECT ap.id FROM ar_payments ap
                    WHERE ap.id = ar_movements.source_id AND ar_movements.source_type = '{$arPaymentClass}'
                ) AS ar_payment_id,
                (SELECT pt.descripcion FROM ar_payments ap
                    JOIN payment_types pt ON pt.id = ap.payment_type_id
                    WHERE ap.id = ar_movements.source_id AND ar_movements.source_type = '{$arPaymentClass}'
                ) AS forma_pago,
                (SELECT ap.referencia FROM ar_payments ap
                    WHERE ap.id = ar_movements.source_id AND ar_movements.source_type = '{$arPaymentClass}'
                ) AS pago_referencia
            ")
            ->toSql();

        $bindings = [$this->clientId];

        $query = DB::table(DB::raw("({$subquery}) as ledger"))
            ->setBindings($bindings)
            ->when($this->search, fn($q) =>
                $q->where('descripcion', 'like', "%{$this->search}%")
            )
            ->when($this->tipo, fn($q) =>
                $q->where('tipo', $this->tipo)
            )
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        $movimientos = $query->paginate($this->perPage);

        // Saldo actual total del cliente
        $saldoActual = ArMovement::where('client_id', $this->clientId)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END), 0) as saldo")
            ->value('saldo') ?? 0;

        $paymentTypes = PaymentType::where('activo', true)->orderBy('descripcion')->get();

        return view('livewire.admin.datatables.ar-client-ledger-table',
            compact('movimientos', 'saldoActual', 'paymentTypes'));
    }
}