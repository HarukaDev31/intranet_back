@php
    $money = fn ($value) => '$'.number_format((float) $value, 2, '.', ',');
    $dmy = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/y') : '—';
    $consolidatedNumber = function ($description) {
        return preg_match('/Consolidado\s*#\s*(\d+)/i', (string) $description, $m) ? $m[1] : '—';
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('china-billing.partials.document-style')
</head>
<body>
    @include('china-billing.partials.document-header')

    <table style="margin: 18px 0 14px;">
        <tr>
            <td>
                <div class="brand">PRO BUSINESS</div>
                <div class="title">BALANCE DE PAGO</div>
            </td>
            <td class="right muted" style="vertical-align: bottom;">Fecha de emisión<br><b style="color: #111;">{{ $dmy($issued_on) }}</b></td>
        </tr>
    </table>

    <table style="border-top: 1px solid #ddd; border-bottom: 1px solid #ddd;">
        <tr>
            <td style="padding: 10px 0;"><div class="label">CLIENTE</div><div style="font-size: 14px; font-weight: bold; margin-top: 2px;">{{ $balance['organization']['name'] }}</div></td>
            <td class="right" style="padding: 10px 0;"><div class="label">CONTACTO</div><div style="margin-top: 2px;">—</div></td>
        </tr>
    </table>

    <table class="data" style="margin-top: 18px;">
        <thead>
            <tr>
                <th>N. OP</th><th>Fecha</th><th>País</th><th>Servicio</th><th>Consolidado</th>
                <th class="right">Monto</th><th class="right">Pagado</th><th class="right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($balance['rows'] as $row)
                <tr>
                    <td><b>{{ $row['code'] }}</b></td>
                    <td>{{ $dmy($row['issued_on']) }}</td>
                    <td>{{ $row['organization']['country'] ?: '—' }}</td>
                    <td>{{ $row['service_name'] }}</td>
                    <td>{{ $consolidatedNumber($row['description']) }}</td>
                    <td class="right">{{ $money($row['total']) }}</td>
                    <td class="right">{{ $money($row['confirmed']) }}</td>
                    <td class="right"><b>{{ $money($row['balance_due']) }}</b></td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted" style="padding: 18px 8px;">El cliente no tiene deudas pendientes.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table style="margin-top: 10px;">
        <tr>
            <td class="right" style="padding-top: 10px; font-size: 11px; font-weight: bold; letter-spacing: 1px;">TOTAL SALDO POR PAGAR (USD)</td>
            <td class="right" style="padding-top: 6px; width: 110px; font-size: 17px; font-weight: bold; color: #f26b1d;">{{ $money($balance['total']) }}</td>
        </tr>
    </table>

    <div style="margin-top: 30px; padding-top: 8px; border-top: 1px solid #eee; font-size: 9px; color: #777;">
        Documento generado por Pro Business · {{ $bank['company'] ?: 'Global Shop International Company Limited' }}.
    </div>
</body>
</html>
