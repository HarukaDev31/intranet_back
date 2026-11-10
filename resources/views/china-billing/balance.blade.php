<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 11px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #666; }
        .box { border: 1px solid #e5e5e5; border-radius: 6px; padding: 10px 12px; margin-top: 12px; }
        table.detail { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.detail th, table.detail td { padding: 5px 4px; border-bottom: 1px solid #eee; text-align: left; }
        table.detail th { color: #666; font-weight: normal; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <p class="muted" style="margin: 0;">{{ $bank['company'] }}</p>
    <h1>Balance de pago</h1>
    <p class="muted">Emitido el {{ $issued_on }}</p>

    <div class="box"><strong>Cliente:</strong> {{ $balance['organization']['name'] }}</div>

    <table class="detail">
        <thead>
            <tr>
                <th>N° OP</th><th>Fecha</th><th>País</th><th>Servicio</th><th>Consolidado</th>
                <th class="right">Monto</th><th class="right">Pagado</th><th class="right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($balance['rows'] as $row)
                <tr>
                    <td>{{ $row['code'] }}</td>
                    <td>{{ $row['issued_on'] }}</td>
                    <td>{{ $row['organization']['country'] ?: '—' }}</td>
                    <td>{{ $row['service_name'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="right">${{ number_format($row['total'], 2, '.', ',') }}</td>
                    <td class="right">${{ number_format($row['confirmed'], 2, '.', ',') }}</td>
                    <td class="right">${{ number_format($row['balance_due'], 2, '.', ',') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Sin operaciones con saldo por pagar.</td></tr>
            @endforelse
            <tr>
                <td colspan="7" class="right"><strong>Total por pagar</strong></td>
                <td class="right"><strong>${{ number_format($balance['total'], 2, '.', ',') }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="box">
        <strong>Cuenta bancaria China</strong>
        <p style="margin: 6px 0 0;">
            Beneficiario: {{ $bank['beneficiary'] ?: '—' }}<br>
            Banco intermediario: {{ $bank['intermediary_bank'] ?: '—' }}<br>
            Banco pagador: {{ $bank['payer_bank'] ?: '—' }}<br>
            Cuenta: {{ $bank['account_number'] ?: '—' }}
        </p>
    </div>
</body>
</html>
