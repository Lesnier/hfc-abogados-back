@component('mail::message')
# Reporte Generado

Tu reporte de empleados ha sido procesado exitosamente.

<div style="text-align: center; width: 100%;">
<a href="{{ $url }}" target="_blank" rel="noopener" style="background-color: #2d3748; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px; font-weight: bold; font-family: Helvetica, Arial, sans-serif;">Descargar PDF</a>
@if(!empty($excelUrl))
<a href="{{ $excelUrl }}" target="_blank" rel="noopener" style="background-color: #38c172; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px; font-weight: bold; font-family: Helvetica, Arial, sans-serif;">Descargar Excel</a>
@endif
</div>
<br>

El enlace expira en 24 horas.

Gracias,<br>
{{ config('app.name') }}
@endcomponent
