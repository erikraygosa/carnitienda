@once
<style>
    .inv-acc { display:flex; flex-wrap:nowrap; align-items:center; gap:5px; }
    .inv-acc form { display:inline-flex; margin:0; }
    .inv-btn {
        display:inline-flex; align-items:center; justify-content:center; gap:4px;
        height:28px; padding:0 9px; border-radius:6px; border:1px solid transparent;
        font-size:12px; font-weight:500; line-height:1; white-space:nowrap; cursor:pointer;
        text-decoration:none; transition:background-color .12s, border-color .12s;
    }
    .inv-btn-blue    { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
    .inv-btn-blue:hover    { background:#dbeafe; }
    .inv-btn-gray    { background:#f9fafb; color:#374151; border-color:#d1d5db; }
    .inv-btn-gray:hover    { background:#f3f4f6; }
    .inv-btn-violet  { background:#f5f3ff; color:#6d28d9; border-color:#ddd6fe; }
    .inv-btn-violet:hover  { background:#ede9fe; }
    .inv-btn-green   { background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
    .inv-btn-green:hover   { background:#d1fae5; }
    .inv-btn-red     { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
    .inv-btn-red:hover     { background:#ffe4e6; }
    .inv-sep { width:1px; height:18px; background:#e5e7eb; margin:0 2px; }
</style>
@endonce

<div class="inv-acc">

    <a href="{{ route('admin.invoices.edit', $invoice) }}" class="inv-btn inv-btn-blue">Editar</a>

    <a href="{{ route('admin.invoices.pdf', $invoice) }}" target="_blank" class="inv-btn inv-btn-gray">Ver PDF</a>
    <a href="{{ route('admin.invoices.download', $invoice) }}" class="inv-btn inv-btn-gray">↓ PDF</a>

    @if(in_array($invoice->estatus, ['TIMBRADA','CANCELACION_PENDIENTE','CANCELADA']) && filled($invoice->xml_timbrado))
        <a href="{{ route('admin.invoices.xml', $invoice) }}" target="_blank" class="inv-btn inv-btn-gray">Ver XML</a>
        <a href="{{ route('admin.invoices.xml.download', $invoice) }}" class="inv-btn inv-btn-gray">↓ XML</a>
    @endif

    @if($invoice->estatus === 'BORRADOR')
        <form action="{{ route('admin.invoices.stamp', $invoice) }}" method="POST">
            @csrf
            <button type="submit" class="inv-btn inv-btn-green">Timbrar</button>
        </form>
    @endif

    @if($invoice->estatus === 'TIMBRADA')
        <a href="{{ route('admin.invoices.send.form', $invoice) }}" class="inv-btn inv-btn-violet">Enviar</a>

        <form action="{{ route('admin.invoices.cancel', $invoice) }}" method="POST" class="form-cancel-cfdi-list">
            @csrf
            <input type="hidden" name="motivo">
            <input type="hidden" name="folio_sustitucion">
            <button type="button" class="inv-btn inv-btn-red" onclick="cancelarCfdiDesdeListado(this)">Cancelar CFDI</button>
        </form>
    @endif

</div>
