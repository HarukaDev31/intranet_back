<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #666; }
        .box { border: 1px solid #e5e5e5; border-radius: 6px; padding: 10px 12px; margin-top: 12px; }
        table.detail { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.detail td, table.detail th { padding: 6px 4px; border-bottom: 1px solid #eee; text-align: left; }
        .right { text-align: right; }
        .total td { font-weight: bold; border-top: 2px solid #1a1a1a; }
    </style>
</head>
<body>
    <p class="muted" style="margin: 0;">{{ $bank['company'] }}</p>
    <h1>Factura {{ $operation['code'] }}</h1>
    <p class="muted">Emitida el {{ $issued_on }} · Fecha de la operación {{ $operation['issued_on'] }}</p>

    <div class="box">
        <strong>Datos del cliente</strong>
        <p style="margin: 6px 0 0;">
            {{ $operation['organization']['name'] }}<br>
            País: {{ $operation['organization']['country'] ?: '—' }}<br>
            Detalle: {{ $operation['description'] }}
        </p>
    </div>

    <table class="detail">
        <tr>
            <td>
                {{ $operation['service_name'] }}@if($operation['variant_name']) ({{ $operation['variant_name'] }})@endif
                @if($operation['billing_type'] === 'cbm') — {{ $operation['cbm'] }} CBM × ${{ number_format($operation['rate'], 2, '.', ',') }}@endif
            </td>
            <td class="right">${{ number_format($operation['service_amount'], 2, '.', ',') }}</td>
        </tr>
        @if($operation['freight'] > 0)
            <tr><td>Flete marítimo</td><td class="right">${{ number_format($operation['freight'], 2, '.', ',') }}</td></tr>
        @endif
        @if($operation['fob'] > 0)
            <tr><td>Gasto FOB</td><td class="right">${{ number_format($operation['fob'], 2, '.', ',') }}</td></tr>
        @endif
        @foreach($operation['costs'] as $cost)
            <tr><td>{{ $cost['name'] }}</td><td class="right">${{ number_format($cost['amount'], 2, '.', ',') }}</td></tr>
        @endforeach
        <tr class="total"><td>Total a pagar</td><td class="right">${{ number_format($operation['total'], 2, '.', ',') }}</td></tr>
        <tr><td class="muted">Pagado a la fecha de emisión</td><td class="right">${{ number_format($operation['confirmed'], 2, '.', ',') }}</td></tr>
        <tr><td><strong>Saldo</strong></td><td class="right"><strong>${{ number_format($operation['balance_due'], 2, '.', ',') }}</strong></td></tr>
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
