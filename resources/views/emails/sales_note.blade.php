<p>Hola {{ $sale->client->nombre ?? '' }},</p>
<p>Te compartimos la nota de venta <b>{{ $sale->folio ?? ('#'.$sale->id) }}</b>.</p>
<p>Saludos.</p>
