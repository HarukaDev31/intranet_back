<table style="border-bottom: 1px solid #ddd;">
    <tr>
        <td style="padding-bottom: 14px;">
            <div style="font-size: 19px; text-transform: uppercase;">{{ $bank['company'] ?: 'Global Shop International Company Limited' }}</div>
            @if($bank['company_address'])
                <div style="font-size: 11px; margin-top: 8px;"><b>OFICINA CHINA:</b> &nbsp; <span style="font-family: 'Noto Sans SC', 'DejaVu Sans', sans-serif;">{{ $bank['company_address'] }}</span></div>
            @endif
        </td>
    </tr>
</table>
