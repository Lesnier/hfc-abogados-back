<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f7f6;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            overflow: hidden; /* Ensure rounded corners if we wanted them */
        }
        .header {          
            padding: 10px 10px 15px 10px;
            text-align: center;
            border-bottom: 2px solid #edeff2;
        }
        .subheader {
            padding: 10px 10px 15px 10px;
            text-align: center;
            border-bottom: 2px solid #edeff2;
            background-color: #333657ff; /* Very Light Grey */
        }
        .header h2 {
            margin: 5px 0 0;
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .subheader h2 {
            margin: 5px 0 0;
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .body {
            padding: 30px 30px 40px;
            color: #555555;
            font-size: 16px;
        }
        .body p {
            margin-bottom: 20px;
        }
        .footer {
            background-color: #1a1a1a;
            padding: 30px 20px;
            text-align: center;
            color: #888888;
            font-size: 13px;
        }
        .footer strong {
            color: #ffffff;
            font-size: 14px;
        }
        .footer p {
            margin: 5px 0;
        }
        /* Button style helper if needed later */
        .btn {
            display: inline-block;
            background-color: #2b3e50;
            color: #ffffff;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <!-- Logo -->
            <div style="margin-bottom: 10px;">
                <img src="https://legalauditex.ar/Logo-LegalAuditex-500px.png" width="180" alt="Legal Auditex" style="border:0; display:inline-block;">
            </div>
            
        </div>
         <div class="subheader">

             <!-- Category -->
                 @if(isset($category))
                <div style="font-size: 11px; font-weight: bold; color: #7c8891ff; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 2px;">
                    {{ $category }}
                </div>
                @endif

            <!-- Date -->
            <div style="font-size: 13px; color: #707070ff; margin-bottom: 8px; text-transform: capitalize;">
                {{ \Carbon\Carbon::now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
            </div>

            <!-- Title -->
            <h2>{{ $title }}</h2>
            </div>
        
        <div class="body">
            {!! $body !!}
        </div>

        <div class="footer">
            <p><strong>{{ $platformName ?? 'Legal Auditex' }}</strong></p>
            @if(isset($lawFirmName))
                <p>{{ $lawFirmName }}</p>
            @endif
            <p style="margin-top: 15px; font-size: 11px; opacity: 0.6;">
                Este es un correo automático, por favor no responda a este mensaje.<br>
                &copy; {{ date('Y') }} Todos los derechos reservados.
            </p>
        </div>
    </div>
</body>
</html>
