    <?php

    use Illuminate\Support\Facades\Route;


    use App\Http\Controllers\SuperAdmin\DashboardController as SuperDashboard;
    use App\Http\Controllers\SuperAdmin\PacController as SuperPac;
    use App\Http\Controllers\SuperAdmin\CompanyController as SuperCompany;
    use App\Http\Controllers\SuperAdmin\SeriesController as SuperSeries;
    use App\Http\Controllers\SuperAdmin\SettingsController as SuperSettings;

    Route::get('/', [SuperDashboard::class, 'index'])->name('dashboard');

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
    });

    Route::prefix('series')->name('series.')->group(function () {
        Route::get('/',           [SuperSeries::class, 'index'])->name('index');
        Route::post('/',          [SuperSeries::class, 'store'])->name('store');
        Route::put('/{serie}',    [SuperSeries::class, 'update'])->name('update');
        Route::delete('/{serie}', [SuperSeries::class, 'destroy'])->name('destroy');
    });

    Route::get('/settings',  [SuperSettings::class, 'index'])->name('settings.index');
    Route::put('/settings',  [SuperSettings::class, 'update'])->name('settings.update');


