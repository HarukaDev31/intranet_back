<table style="border-bottom: 1px solid #ddd;">
    <tr>
        <td style="padding-bottom: 14px;">
            <div style="font-size: 19px; text-transform: uppercase;">{{ $bank['company'] ?: 'Global Shop International Company Limited' }}</div>
            @if($bank['company_address'])
                <div style="font-size: 11px; margin-top: 8px;"><b>OFICINA CHINA:</b> &nbsp; {{ $bank['company_address'] }}</div>
            @endif
        </td>
    </tr>
</table>
