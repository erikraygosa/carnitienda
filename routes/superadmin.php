<?php

    use Illuminate\Support\Facades\Route;


    use App\Http\Controllers\SuperAdmin\DashboardController as SuperDashboard;
    use App\Http\Controllers\SuperAdmin\PacController as SuperPac;
    use App\Http\Controllers\SuperAdmin\CompanyController as SuperCompany;
    use App\Http\Controllers\SuperAdmin\SeriesController as SuperSeries;
    use App\Http\Controllers\SuperAdmin\SettingsController as SuperSettings;
    use App\Http\Controllers\SuperAdmin\ResetController as SuperReset;
    use App\Http\Controllers\SuperAdmin\ProductCatalogController as SuperProducts;
    use App\Http\Controllers\SuperAdmin\ArMigrationController as SuperArMigration;

    Route::get('/', [SuperDashboard::class, 'index'])->name('dashboard');

    Route::prefix('reset')->name('reset.')->group(function () {
        Route::get('/',  [SuperReset::class, 'index'])->name('index');
        Route::post('/', [SuperReset::class, 'run'])->name('run');
    });

    Route::prefix('pac')->name('pac.')->group(function () {
        Route::get('/',               [SuperPac::class, 'index'])->name('index');
        Route::put('/{pac}',          [SuperPac::class, 'update'])->name('update');
        Route::post('/{pac}/activar', [SuperPac::class, 'activar'])->name('activar');
        Route::post('/{pac}/test',    [SuperPac::class, 'test'])->name('test');
    });

    Route::prefix('empresas')->name('companies.')->group(function () {
        Route::get('/',                       [SuperCompany::class, 'index'])->name('index');
        Route::post('/{company}/toggle',      [SuperCompany::class, 'toggle'])->name('toggle');
        Route::post('/{company}/timbres',     [SuperCompany::class, 'addTimbres'])->name('timbres');
        Route::get('/{company}/consumo',      [SuperCompany::class, 'consumo'])->name('consumo');
    });

    Route::prefix('series')->name('series.')->group(function () {
        Route::get('/',           [SuperSeries::class, 'index'])->name('index');
        Route::post('/',          [SuperSeries::class, 'store'])->name('store');
        Route::put('/{serie}',    [SuperSeries::class, 'update'])->name('update');
        Route::delete('/{serie}', [SuperSeries::class, 'destroy'])->name('destroy');
        Route::post('/{serie}/reset', [SuperSeries::class, 'reset'])->name('reset');
    });

    Route::prefix('productos')->name('products.')->group(function () {
        Route::get('/',           [SuperProducts::class, 'index'])->name('index');
        Route::get('/exportar',   [SuperProducts::class, 'export'])->name('export');
        Route::get('/importar',   [SuperProducts::class, 'importForm'])->name('import.form');
        Route::post('/importar',  [SuperProducts::class, 'import'])->name('import');
    });

    Route::prefix('cxc-migradas')->name('ar-migration.')->group(function () {
        Route::get('/',              [SuperArMigration::class, 'index'])->name('index');
        Route::post('/',             [SuperArMigration::class, 'store'])->name('store');
        Route::get('/plantilla',     [SuperArMigration::class, 'plantilla'])->name('plantilla');
        Route::post('/importar',     [SuperArMigration::class, 'importar'])->name('importar');
        Route::get('/{order}/editar', [SuperArMigration::class, 'edit'])->name('edit');
        Route::put('/{order}',        [SuperArMigration::class, 'update'])->name('update');
        Route::delete('/{order}',     [SuperArMigration::class, 'destroy'])->name('destroy');
    });

    Route::get('/settings',  [SuperSettings::class, 'index'])->name('settings.index');
    Route::put('/settings',  [SuperSettings::class, 'update'])->name('settings.update');
    Route::post('/settings/whatsapp/test', [SuperSettings::class, 'testWhatsapp'])->name('settings.whatsapp.test');
    Route::post('/settings/printer/test',  [SuperSettings::class, 'testPrinter'])->name('settings.printer.test');
    Route::post('/settings/assistant/test', [SuperSettings::class, 'testAssistant'])->name('settings.assistant.test');


