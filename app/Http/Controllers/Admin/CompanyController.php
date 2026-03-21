<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCompanyCertificateRequest;
use App\Http\Requests\Admin\SaveCompanyFiscalDataRequest;
use App\Http\Requests\Admin\SaveCompanyRequest;
use App\Models\Company;
use App\Models\CompanyCertificate;
use App\Models\CompanyFiscalData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        $companies = Company::withCount('users')
            ->with(['fiscalData', 'csdActivo'])
            ->latest()
            ->paginate(15);

        return view('parametros.companies.index', compact('companies'));
    }

    public function create(): View
    {
        return view('parametros.companies.create');
    }

    public function store(SaveCompanyRequest $request): RedirectResponse
    {
        $company = Company::create($request->validated());

        $company->users()->attach(auth()->id(), [
            'es_admin'          => true,
            'es_empresa_activa' => true,
        ]);

        return redirect()
            ->route('admin.parametros.companies.fiscal', $company)
            ->with('success', 'Empresa creada. Ahora configura los datos fiscales.');
    }

    public function edit(Company $company): View
    {
        return view('parametros.companies.edit', compact('company'));
    }

    public function update(SaveCompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return redirect()
            ->route('admin.parametros.companies.edit', $company)
            ->with('success', 'Datos de la empresa actualizados.');
    }

    // ------------------------------------------------------------------
    // Datos fiscales
    // ------------------------------------------------------------------

    public function fiscalEdit(Company $company): View
    {
        $fiscalData = $company->fiscalData
            ?? new CompanyFiscalData(['company_id' => $company->id]);

        $regimenes = CompanyFiscalData::regimenesParaTipo($company->tipo_persona);

        return view('parametros.companies.fiscal', compact('company', 'fiscalData', 'regimenes'));
    }

    public function fiscalUpdate(SaveCompanyFiscalDataRequest $request, Company $company): RedirectResponse
    {
        $company->fiscalData()->updateOrCreate(
            ['company_id' => $company->id],
            $request->validated()
        );

        return redirect()
            ->route('admin.parametros.companies.certificates', $company)
            ->with('success', 'Datos fiscales guardados.');
    }

    // ------------------------------------------------------------------
    // Certificados
    // ------------------------------------------------------------------

    public function certificatesIndex(Company $company): View
    {
        $certificates = $company->certificates()->latest()->get();
        $csd          = $company->csdActivo;
        $fiel         = $company->fielActiva;

        return view('parametros.companies.certificates', compact('company', 'certificates', 'csd', 'fiel'));
    }

    public function certificateStore(SaveCompanyCertificateRequest $request, Company $company): RedirectResponse
    {
        DB::transaction(function () use ($request, $company) {
            $tipo    = $request->tipo;
            $baseDir = "private/certs/{$company->id}/{$tipo}";

            $cerPath = $request->file('archivo_cer')
                ->storeAs($baseDir, 'certificado.cer', 'local');

            $keyPath = $request->file('archivo_key')
                ->storeAs($baseDir, 'llave.key', 'local');

            $company->certificates()
                ->where('tipo', $tipo)
                ->where('activo', true)
                ->update(['activo' => false]);

            $cert = $company->certificates()->create([
                'tipo'        => $tipo,
                'cer_path'    => $cerPath,
                'key_path'    => $keyPath,
                'activo'      => true,
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
            ]);

            $cert->setPassword($request->password);
            $cert->sincronizarMetadatos();
        });

        return redirect()
            ->route('admin.parametros.companies.certificates', $company)
            ->with('success', 'Certificado ' . strtoupper($request->tipo) . ' cargado correctamente.');
    }

    public function certificateDestroy(Company $company, CompanyCertificate $certificate): RedirectResponse
    {
        $certificate->delete();

        return redirect()
            ->route('admin.parametros.companies.certificates', $company)
            ->with('success', 'Certificado eliminado.');
    }
}