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
            background-color: #2b3e50; /* Dark Blue */
            padding: 40px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h2 {
            margin: 10px 0 0;
            font-size: 24px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header .category {
            font-size: 12px;
            text-transform: uppercase;
            color: #a3b6c5; /* Lighter blue/gray for subtitle */
            letter-spacing: 2px;
            font-weight: 600;
        }
        .body {
            padding: 40px 30px;
            color: #555555;
            font-size: 16px;
        }
        .body p {
            margin-bottom: 20px;
        }
        .footer {
            background-color: #1a1a1a; /* Black/Dark */
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
            <div style="font-size: 13px; color: #a3b6c5; margin-bottom: 10px; text-transform: capitalize;">
                {{ \Carbon\Carbon::now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
            </div>
            @if(isset($category))
                <div class="category">{{ $category }}</div>
            @endif
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
