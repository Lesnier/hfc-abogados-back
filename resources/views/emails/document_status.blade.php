<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #ffffff; padding: 20px; border-radius: 5px; max-width: 600px; margin: 0 auto; }
        h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .approved { color: green; font-weight: bold; }
        .rejected { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reporte de Estado de Documentación</h2>
        <p>Estimado Proveedor,</p>
        <p>Se ha realizado una revisión de la documentación del empleado <strong>{{ $employee->name }}</strong> (DNI/ID: {{ $employee->identification }}).</p>
        
        <p><strong>Versión de Documentos:</strong> {{ $docVersion->version_number }}<br>
        <strong>Fecha:</strong> {{ $docVersion->effective_date->format('d/m/Y') }}</p>

        <table>
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Estado</th>
                    <th>Nota/Observación</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $docLabels = [
                        'form_931' => 'Form. 931',
                        'policy' => 'Póliza',
                        'life_insurance' => 'Seguro de Vida',
                        'salary_receipt' => 'Recibo de Sueldo',
                        'repetition' => 'Repetición',
                        'indemnity' => 'Indemnidad',
                        'proof_discharge' => 'Comprobante de Alta',
                        'arca_termination_form' => 'Form. Baja ARCA'
                    ];
                @endphp
                @foreach($files as $file)
                    <tr>
                        <td>{{ $docLabels[$file->doc_type] ?? $file->doc_type }}</td>
                        <td class="{{ $file->is_approved ? 'approved' : 'rejected' }}">
                            {{ $file->is_approved ? 'Aprobado' : 'No Aprobado' }}
                        </td>
                        <td>{{ $file->note ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p>Por favor, revise los documentos marcados como "No Aprobado" y las observaciones adjuntas.</p>
        <br>
        <p>Atentamente,<br>Equipo de Gestión</p>
    </div>
</body>
</html>
