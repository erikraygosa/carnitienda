<?php

namespace App\Console\Commands;

use App\Models\SalesOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InventarioDuplicados extends Command
{
    protected $signature   = 'inventario:duplicados {--desde= : Fecha inicial (Y-m-d)}';
    protected $description = 'Lista pedidos cuyo inventario se descontó más de una vez en Salida de producto (solo lectura).';

    public function handle(): int
    {
        $q = DB::table('document_activity_logs')
            ->where('document_type', SalesOrder::class)->where('action', 'salida_de_producto')
            ->when($this->option('desde'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->groupBy('document_id')->havingRaw('COUNT(*) > 1');

        $filas = []; $totalExtra = 0.0;
        foreach ($q->pluck('document_id') as $id) {
            $o = SalesOrder::with('items')->find($id);
            if (! $o) continue;
            $mov = DB::table('stock_movements')->where('referencia_type', SalesOrder::class)
                ->where('referencia_id', $id)->where('motivo', 'VENTA_PADRE')->get();
            foreach ($o->items->groupBy('product_id') as $pid => $its) {
                if (! $pid) continue;
                $m = $mov->where('product_id', $pid);
                if ($m->count() <= $its->count()) continue;
                $esperado = (float) $its->sum('cantidad'); $real = (float) $m->sum('cantidad');
                $extra = max(0, $real - $esperado); $totalExtra += $extra;
                $filas[] = [$o->folio, $o->status, $pid, round($esperado, 3), round($real, 3), round($extra, 3)];
            }
        }

        $this->table(['Pedido', 'Status', 'Producto', 'Esperado', 'Descontado', 'De más'], $filas);
        $this->info(count($filas) . ' producto(s) con descuento de más; total aproximado: ' . round($totalExtra, 2));

        return self::SUCCESS;
    }
}
