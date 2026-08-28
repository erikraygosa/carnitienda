<div>
    <div class="overflow-auto rounded-lg border">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="p-3 text-left font-medium text-gray-600">Fecha</th>
                    <th class="p-3 text-left font-medium text-gray-600">Tipo</th>
                    <th class="p-3 text-left font-medium text-gray-600">Descripción</th>
                    <th class="p-3 text-left font-medium text-gray-600">Referencia</th>
                    <th class="p-3 text-left font-medium text-gray-600">Forma de pago</th>
                    <th class="p-3 text-right font-medium text-gray-600">Cargo</th>
                    <th class="p-3 text-right font-medium text-gray-600">Abono</th>
                    <th class="p-3 text-right font-medium text-gray-600">Saldo</th>
                    @can('registrar cobros')
                        <th class="p-3 text-center font-medium text-gray-600">Editar</th>
                    @endcan
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($movimientos as $mov)
                    @if($editingPaymentId && $mov->ar_payment_id == $editingPaymentId)
                        <tr class="bg-amber-50">
                            <td class="p-3">
                                <input type="date" wire:model="editFecha"
                                       class="w-36 rounded-md border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                @error('editFecha') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">ABONO</span>
                            </td>
                            <td class="p-3 text-gray-500 text-xs">{{ $mov->descripcion ?? '—' }}</td>
                            <td class="p-3">
                                <input type="text" wire:model="editReferencia" placeholder="Referencia"
                                       class="w-32 rounded-md border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="p-3">
                                <select wire:model="editPaymentTypeId"
                                        class="w-36 rounded-md border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach($paymentTypes as $pt)
                                        <option value="{{ $pt->id }}">{{ $pt->descripcion }}</option>
                                    @endforeach
                                </select>
                                @error('editPaymentTypeId') <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p> @enderror
                            </td>
                            <td class="p-3 text-right text-gray-200">—</td>
                            <td class="p-3 text-right text-emerald-700 font-medium whitespace-nowrap">
                                ${{ number_format($mov->monto, 2) }}
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                ${{ number_format($mov->saldo_acumulado, 2) }}
                            </td>
                            <td class="p-3 text-center whitespace-nowrap">
                                <button wire:click="editSave" class="text-emerald-600 hover:text-emerald-800 mr-2" title="Guardar">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                                <button wire:click="editCancel" class="text-gray-400 hover:text-gray-600" title="Cancelar">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </td>
                        </tr>
                    @else
                    <tr class="hover:bg-gray-50 {{ $mov->tipo === 'CARGO' ? '' : 'bg-emerald-50/40' }}">
                        <td class="p-3 text-gray-500 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($mov->fecha)->format('d/m/Y') }}
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $mov->tipo === 'CARGO'
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-emerald-100 text-emerald-700' }}">
                                {{ $mov->tipo }}
                            </span>
                        </td>
                        <td class="p-3 text-gray-700 max-w-xs truncate">
                            {{ $mov->descripcion ?? '—' }}
                        </td>
                        <td class="p-3 text-gray-400 text-xs">
                            @if($mov->source_type && $mov->source_id)
                                @php
                                    $label = class_basename($mov->source_type);
                                    $label = match($label) {
                                        'SalesOrder' => 'Pedido',
                                        'Sale'       => 'Venta',
                                        'ArPayment'  => 'Pago',
                                        'Invoice'    => 'Factura',
                                        default      => $label,
                                    };
                                @endphp
                                <span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">
                                    {{ $label }} #{{ $mov->source_id }}
                                </span>
                                @if($mov->pago_referencia)
                                    <span class="block text-gray-400 mt-0.5">Ref: {{ $mov->pago_referencia }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="p-3 text-xs">
                            @if($mov->tipo === 'ABONO')
                                @if($mov->forma_pago)
                                    <span class="px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 font-medium">{{ $mov->forma_pago }}</span>
                                @else
                                    <span class="text-gray-300">Sin registrar</span>
                                @endif
                            @else
                                <span class="text-gray-200">—</span>
                            @endif
                        </td>
                        <td class="p-3 text-right">
                            @if($mov->tipo === 'CARGO')
                                <span class="text-red-700 font-medium">
                                    ${{ number_format($mov->monto, 2) }}
                                </span>
                            @else
                                <span class="text-gray-200">—</span>
                            @endif
                        </td>
                        <td class="p-3 text-right">
                            @if($mov->tipo === 'ABONO')
                                <span class="text-emerald-700 font-medium">
                                    ${{ number_format($mov->monto, 2) }}
                                </span>
                            @else
                                <span class="text-gray-200">—</span>
                            @endif
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            @if($mov->saldo_acumulado > 0)
                                <span class="font-semibold text-gray-800">
                                    ${{ number_format($mov->saldo_acumulado, 2) }}
                                </span>
                            @elseif($mov->saldo_acumulado < 0)
                                <span class="font-semibold text-emerald-600">
                                    (${{ number_format(abs($mov->saldo_acumulado), 2) }})
                                </span>
                            @else
                                <span class="text-gray-400">$0.00</span>
                            @endif
                        </td>
                        @can('registrar cobros')
                            <td class="p-3 text-center">
                                @if($mov->tipo === 'ABONO' && $mov->ar_payment_id)
                                    <button wire:click="editStart({{ $mov->ar_payment_id }})"
                                            class="text-gray-400 hover:text-indigo-600" title="Editar cobro">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                @endif
                            </td>
                        @endcan
                    </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="9" class="p-6 text-center text-gray-400">
                            Sin movimientos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if($movimientos->count())
            <tfoot class="border-t bg-gray-50">
                <tr>
                    <td colspan="5" class="p-3 text-sm font-medium text-right text-gray-600">
                        Totales de esta página:
                    </td>
                    <td class="p-3 text-right font-semibold text-red-700">
                        ${{ number_format($movimientos->where('tipo','CARGO')->sum('monto'), 2) }}
                    </td>
                    <td class="p-3 text-right font-semibold text-emerald-700">
                        ${{ number_format($movimientos->where('tipo','ABONO')->sum('monto'), 2) }}
                    </td>
                    <td class="p-3 text-right font-semibold text-gray-800">
                        ${{ number_format($saldoActual, 2) }}
                    </td>
                    @can('registrar cobros')
                        <td></td>
                    @endcan
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    <div class="mt-4">
        {{ $movimientos->links() }}
    </div>
</div>