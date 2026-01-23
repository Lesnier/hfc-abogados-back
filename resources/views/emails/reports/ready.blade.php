@component('mail::message')
# Reporte Generado

Tu reporte de empleados ha sido procesado exitosamente.

@component('mail::button', ['url' => $url])
Descargar PDF
@endcomponent

El enlace expira en 24 horas.

Gracias,<br>
{{ config('app.name') }}
@endcomponent
