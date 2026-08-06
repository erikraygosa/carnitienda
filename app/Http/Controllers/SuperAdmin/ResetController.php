<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Reinicio de datos operativos (superadmin). Borra TRANSACCIONES reales
 * (pedidos, despachos, CxC, caja/POS/inventario, facturas) pero jamás toca
 * catálogo: clientes, productos, categorías, almacenes, usuarios, listas de
 * precios ni configuración. Antes de borrar, exporta un respaldo JSON de
 * cada tabla afectada a storage/app/private/reset-backups/.
 *
 * Requiere confirmación escrita ("REINICIAR") en cada ejecución.
 */
class ResetController extends Controller
{
    /**
     * Cada módulo: [label, tablas en orden hijo→padre].
     * document_activity_logs (bitácora) se limpia siempre que se borre algo,
     * porque queda apuntando a documentos que ya no existen.
     */
    private const MODULES = [
        'pedidos' => [
            'label'  => 'Pedidos y cotizaciones',
            'tables' => ['sales_order_items', 'sales_orders', 'quote_items', 'quotes'],
        ],
        'despachos' => [
            'label'  => 'Despachos y logística',
            'tables' => ['dispatch_item_lines', 'dispatch_items', 'dispatch_ar_assignments', 'dispatch_transfer_assignments', 'dispatches'],
        ],
        'cxc' => [
            'label'  => 'Cuentas por cobrar',
            'tables' => ['ar_payment_items', 'ar_payments', 'ar_movements'],
        ],
        'caja_pos_inventario' => [
            'label'  => 'Caja, POS e inventario',
            'tables' => [
                'cash_movements', 'cash_registers',
                'driver_cash_movements', 'driver_cash_registers',
                'sale_items', 'sales',
                'pos_sale_items', 'pos_sales',
                'stock_movements',
                'stock_transfer_items', 'stock_transfers',
            ],
        ],
        'facturas' => [
            'label'  => 'Facturas (CFDI) — solo si nunca se timbró algo real',
            'tables' => ['invoice_complement_docs', 'invoice_items', 'invoices'],
        ],
    ];

    private const AUDIT_TABLE = 'document_activity_logs';

    public function index()
    {
        $counts = [];
        foreach (self::MODULES as $key => $mod) {
            foreach ($mod['tables'] as $table) {
                $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : null;
            }
        }
        $auditCount = Schema::hasTable(self::AUDIT_TABLE) ? DB::table(self::AUDIT_TABLE)->count() : null;

        return view('superadmin.reset.index', [
            'modules'     => self::MODULES,
            'counts'      => $counts,
            'auditTable'  => self::AUDIT_TABLE,
            'auditCount'  => $auditCount,
        ]);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'modules'       => ['required', 'array', 'min:1'],
            'modules.*'     => ['in:' . implode(',', array_keys(self::MODULES))],
            'confirmacion'  => ['required', 'string'],
        ]);

        if (trim($data['confirmacion']) !== 'REINICIAR') {
            return back()->withInput()->with('error', 'Confirmación incorrecta: debes escribir exactamente "REINICIAR" para continuar.');
        }

        // Reunir tablas de los módulos elegidos, en el orden ya child→parent que definimos.
        $tables = [];
        foreach ($data['modules'] as $key) {
            foreach (self::MODULES[$key]['tables'] as $t) {
                $tables[] = $t;
            }
        }
        $tables[] = self::AUDIT_TABLE;
        $tables = array_values(array_unique($tables));

        set_time_limit(180);

        $stamp     = now()->format('Y-m-d_His');
        $backupDir = "reset-backups/{$stamp}";
        $manifest  = ['fecha' => now()->toDateTimeString(), 'usuario' => auth()->user()->email ?? auth()->id(), 'tablas' => []];

        // 1) Respaldo JSON de cada tabla ANTES de borrar nada.
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;
            $rows = DB::table($table)->get();
            Storage::disk('local')->put("{$backupDir}/{$table}.json", $rows->toJson(JSON_PRETTY_PRINT));
            $manifest['tablas'][$table] = $rows->count();
        }
        Storage::disk('local')->put("{$backupDir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 2) Truncar (resetea también los autoincrementales, para folios "desde 0").
        //    FK checks off por si alguna tabla tiene constraint que no mapeamos.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        return redirect()->route('superadmin.reset.index')->with('success',
            'Datos reiniciados. Respaldo guardado en storage/app/reset-backups/' . $stamp .
            ' — se vaciaron ' . count($tables) . ' tablas. Clientes, productos y configuración quedaron intactos.'
        );
    }
}
