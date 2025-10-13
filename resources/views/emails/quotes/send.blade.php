<p>Hola {{ $quote->client->nombre ?? '' }},</p>
<p>Adjuntamos tu cotización. ¡Gracias por tu preferencia!</p>
<p>Saludos,</p>
<p>{{ config('app.name') }}</p>
