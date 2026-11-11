@php
    $money = fn ($value) => '$'.number_format((float) $value, 2, '.', ',');
    $dmy = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/y') : '—';
    $op = $operation;
    $isCbm = $op['billing_type'] === 'cbm';
    preg_match('/Consolidado\s*#\s*\d+/i', (string) $op['description'], $match);
    $consolidated = $match[0] ?? $op['description'];

    $mainRows = $isCbm
        ? [
            ['Cantidad de CBM', number_format((float) $op['cbm'], 2).' CBM × '.$money($op['rate']), $op['service_amount']],
            ['Flete marítimo', '—', $op['freight']],
            ['Gasto FOB', '—', $op['fob']],
        ]
        : [
            [$op['service_name'].($op['variant_name'] ? ' ('.$op['variant_name'].')' : ''), 'Monto del servicio', $op['service_amount']],
            ['Flete marítimo', '—', $op['freight']],
            ['Gasto FOB', '—', $op['fob']],
        ];
    $mainSubtotal = collect($mainRows)->sum(fn ($row) => (float) $row[2]);
    $otherRows = collect($op['costs'])->map(fn ($cost) => [$cost['name'] ?: 'Costo adicional', '—', $cost['amount']])->all();
    $otherSubtotal = collect($otherRows)->sum(fn ($row) => (float) $row[2]);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('china-billing.partials.document-style')
</head>
<body>
    @include('china-billing.partials.document-header')

    <table style="margin: 12px 0 10px;">
        <tr>
            <td>
                <div class="brand">PRO BUSINESS</div>
                <div class="title">FACTURA CONSOLIDADO</div>
            </td>
            <td class="right muted" style="vertical-align: bottom;">N° {{ $op['code'] }}<br>Fecha <b style="color: #111;">{{ $dmy($op['issued_on']) }}</b></td>
        </tr>
    </table>

    <table style="border-top: 1px solid #ddd; border-bottom: 1px solid #ddd;">
        <tr><td colspan="4" class="label" style="padding: 10px 0 6px;">IMPORTADOR</td></tr>
        <tr>
            <td style="width: 25%; padding-bottom: 10px;"><div class="label">EMPRESA</div><b>{{ $op['organization']['name'] }}</b></td>
            <td style="width: 25%; padding-bottom: 10px;"><div class="label">CONSOLIDADO</div><b>{{ $consolidated }}</b></td>
            <td style="width: 25%; padding-bottom: 10px;"><div class="label">CONTACTO</div><b>—</b></td>
            <td style="width: 25%; padding-bottom: 10px;"><div class="label">PAÍS</div><b>{{ $op['organization']['country'] ?: '—' }}</b></td>
        </tr>
    </table>

    <table class="data" style="margin-top: 10px;">
        <thead><tr><th>Concepto</th><th>Detalle</th><th class="right">Monto USD</th></tr></thead>
        <tbody>
            @foreach($mainRows as $row)
                <tr><td>{{ $row[0] }}</td><td class="muted">{{ $row[1] }}</td><td class="right">{{ $money($row[2]) }}</td></tr>
            @endforeach
            <tr><td colspan="2" class="right muted" style="font-size: 10px;">Subtotal</td><td class="right"><b>{{ $money($mainSubtotal) }}</b></td></tr>
        </tbody>
    </table>

    <div style="font-size: 10px; font-weight: bold; letter-spacing: 1px; margin: 10px 0 3px;">OTROS GASTOS</div>
    <table class="data">
        <thead><tr><th>Concepto</th><th>Detalle</th><th class="right">Monto USD</th></tr></thead>
        <tbody>
            @forelse($otherRows as $row)
                <tr><td>{{ $row[0] }}</td><td class="muted">{{ $row[1] }}</td><td class="right">{{ $money($row[2]) }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">Sin otros gastos.</td></tr>
            @endforelse
            <tr><td colspan="2" class="right muted" style="font-size: 10px;">Subtotal otros gastos</td><td class="right"><b>{{ $money($otherSubtotal) }}</b></td></tr>
        </tbody>
    </table>

    <table style="margin-top: 14px; border-top: 2px solid #111;">
        <tr>
            <td class="right" style="padding-top: 10px; font-size: 11px; font-weight: bold; letter-spacing: 1px;">TOTAL A PAGAR (USD)</td>
            <td class="right" style="padding-top: 8px; width: 120px; font-size: 18px; font-weight: bold; color: #f26b1d;">{{ $money($mainSubtotal + $otherSubtotal) }}</td>
        </tr>
    </table>

    <div style="margin-top: 14px; background: #111; color: #fff; text-align: center; font-size: 10px; font-weight: bold; letter-spacing: 1px; padding: 7px;">NUESTRA CUENTA BANCARIA EN CHINA</div>

    <div class="bank-title">*DATOS DEL BANCO INTERMEDIARIO</div>
    <table class="bank">
        <tr><td class="k">Código Swift:</td><td>{{ $bank['intermediary_swift'] ?: '—' }}</td></tr>
        <tr><td class="k">Nombre del banco:</td><td>{{ $bank['intermediary_bank'] ?: '—' }}</td></tr>
        <tr><td class="k">País:</td><td>{{ $bank['intermediary_country'] ?: '—' }}</td></tr>
        <tr><td class="k">Estado:</td><td>{{ $bank['intermediary_state'] ?: '—' }}</td></tr>
        <tr><td class="k">Ciudad:</td><td>{{ $bank['intermediary_city'] ?: '—' }}</td></tr>
    </table>

    <div class="bank-title">*DATOS DEL BANCO PAGADOR</div>
    <table class="bank">
        <tr><td class="k">Código Swift:</td><td>{{ $bank['payer_swift'] ?: '—' }}</td></tr>
        <tr><td class="k">Nombre del banco:</td><td>{{ $bank['payer_bank'] ?: '—' }}</td></tr>
        <tr><td class="k">País:</td><td>{{ $bank['payer_country'] ?: '—' }}</td></tr>
        <tr><td class="k">Estado:</td><td>{{ $bank['payer_state'] ?: '—' }}</td></tr>
        <tr><td class="k">Ciudad:</td><td>{{ $bank['payer_city'] ?: '—' }}</td></tr>
    </table>

    <div class="bank-title">*DATOS DEL BENEFICIARIO</div>
    <table class="bank">
        <tr><td class="k">Cuenta:</td><td>{{ $bank['account_number'] ?: '—' }}</td></tr>
        <tr><td class="k">Beneficiario:</td><td>{{ $bank['beneficiary'] ?: '—' }}</td></tr>
        <tr><td class="k">Dirección:</td><td>{{ $bank['beneficiary_address'] ?: '—' }}</td></tr>
        <tr><td class="k">País:</td><td>{{ $bank['beneficiary_country'] ?: '—' }}</td></tr>
        <tr><td class="k">Estado:</td><td>{{ $bank['beneficiary_state'] ?: '—' }}</td></tr>
        <tr><td class="k">Ciudad:</td><td>{{ $bank['beneficiary_city'] ?: '—' }}</td></tr>
    </table>

    <div style="margin-top: 12px; padding-top: 6px; border-top: 1px solid #eee; font-size: 9px; color: #777;">
        Documento generado por Pro Business · {{ $bank['company'] ?: 'Global Shop International Company Limited' }}.
    </div>
</body>
</html>
