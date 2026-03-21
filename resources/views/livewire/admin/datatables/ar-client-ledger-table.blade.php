<div>
    <div class="overflow-auto rounded-lg border">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="p-3 text-left font-medium text-gray-600">Fecha</th>
                    <th class="p-3 text-left font-medium text-gray-600">Tipo</th>
                    <th class="p-3 text-left font-medium text-gray-600">Descripción</th>
                    <th class="p-3 text-left font-medium text-gray-600">Referencia</th>
                    <th class="p-3 text-right font-medium text-gray-600">Cargo</th>
                    <th class="p-3 text-right font-medium text-gray-600">Abono</th>
                    <th class="p-3 text-right font-medium text-gray-600">Saldo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($movimientos as $mov)
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
                            @else
                                —
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
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-gray-400">
                            Sin movimientos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if($movimientos->count())
            <tfoot class="border-t bg-gray-50">
                <tr>
                    <td colspan="4" class="p-3 text-sm font-medium text-right text-gray-600">
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
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    <div class="mt-4">
        {{ $movimientos->links() }}
    </div>
</div>