<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 36px 40px 48px 40px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1e293b;
            line-height: 1.5;
        }
        .cover {
            page-break-after: always;
            text-align: center;
            padding-top: 120px;
        }
        .cover-brand {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 28px;
        }
        .cover-logo {
            max-height: 56px;
            margin-bottom: 28px;
        }
        .cover h1 {
            font-size: 26px;
            color: #0f172a;
            margin: 0 0 12px;
            line-height: 1.25;
        }
        .cover .sub {
            font-size: 13px;
            color: #475569;
            margin: 0 auto 24px;
            max-width: 420px;
        }
        .cover-meta {
            display: inline-block;
            margin-top: 36px;
            padding: 10px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 10px;
            color: #64748b;
        }
        .cover-line {
            width: 64px;
            height: 3px;
            background: #2563eb;
            margin: 18px auto 22px;
        }
        .toc-page {
            page-break-after: always;
        }
        .toc-page h2 {
            font-size: 16px;
            color: #0f172a;
            margin: 0 0 14px;
            padding-bottom: 6px;
            border-bottom: 2px solid #2563eb;
        }
        .toc-item {
            margin: 0 0 10px;
            page-break-inside: avoid;
        }
        .toc-item-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }
        .toc-child {
            margin: 3px 0 0 14px;
            font-size: 10px;
            color: #475569;
        }
        .role-block { page-break-before: always; }
        .role-block.first { page-break-before: auto; }
        .role-heading {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 6px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e2e8f0;
        }
        .role-desc { color: #64748b; margin: 0 0 16px; font-size: 10px; }
        .chapter { margin-bottom: 18px; page-break-inside: avoid; }

        .page-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        .page-card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 12px;
        }
        .page-card-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .page-card-desc {
            font-size: 10px;
            color: #64748b;
            margin-top: 3px;
        }
        .page-card-body { padding: 12px; }

        .grupo {
            margin: 0 0 14px;
            page-break-inside: avoid;
        }
        .grupo-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }
        .grupo-clave {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }
        .grupo-sub { font-size: 9.5px; color: #64748b; margin-top: 2px; }
        .grupo-children {
            margin-top: 8px;
            padding-left: 10px;
            border-left: 2px solid #e2e8f0;
        }
        .grupo-nested { margin-top: 10px; }

        .widget { margin: 0 0 12px; page-break-inside: avoid; }
        .widget-title { font-size: 11px; font-weight: bold; color: #0f172a; margin-bottom: 4px; }
        .muted { color: #64748b; font-size: 9.5px; margin-bottom: 6px; }
        .texto { font-size: 10.5px; color: #334155; }

        .callout {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            border-radius: 8px;
            padding: 8px 10px;
            margin: 6px 0;
        }
        .callout-warning { border-color: #fde68a; background: #fffbeb; }
        .callout-danger { border-color: #fecaca; background: #fef2f2; }
        .callout-title { font-weight: bold; margin-bottom: 3px; }

        .toolbar { margin: 4px 0 8px; }
        .btn {
            display: inline-block;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 9px;
            margin: 0 4px 4px 0;
        }
        .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
        }

        .tabs { margin: 4px 0 10px; }
        .tab {
            display: inline-block;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 9px;
            margin-right: 4px;
            color: #475569;
        }
        .tab-active {
            background: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
            font-weight: bold;
        }

        .filters { margin: 4px 0 8px; }
        .filter-field {
            display: inline-block;
            width: 31%;
            vertical-align: top;
            margin: 0 1.5% 8px 0;
        }
        .filter-label { font-size: 9px; color: #64748b; margin-bottom: 2px; }
        .filter-control {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 5px 7px;
            background: #ffffff;
            font-size: 9.5px;
            color: #334155;
            min-height: 14px;
        }

        .data-table {
            border-collapse: collapse;
            width: 100%;
            font-size: 8.5px;
            margin: 4px 0 8px;
        }
        .data-table th {
            background: #f1f5f9;
            color: #0f172a;
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #e2e8f0;
            font-weight: bold;
        }
        .data-table td {
            padding: 5px 6px;
            border: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) td { background: #f8fafc; }
        .pill {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 1px 6px;
            font-size: 8px;
        }

        .media { margin: 6px 0; text-align: center; }
        .media img {
            max-width: 100%;
            height: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .media-caption { font-size: 9px; color: #64748b; text-align: center; margin-top: 4px; }
        .media-placeholder {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 18px;
            color: #94a3b8;
            text-align: center;
        }

        .embed-box, .card-box, .modal-box {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 10px;
            background: #ffffff;
            margin: 4px 0;
        }
        .modal-title { font-weight: bold; margin-bottom: 6px; font-size: 11px; }
        .card-row { margin-bottom: 4px; }
        .card-label { color: #64748b; font-size: 9px; }
        .card-value { color: #0f172a; }

        .flow { margin: 6px 0; }
        .flow-step { margin-bottom: 8px; page-break-inside: avoid; }
        .flow-num {
            display: inline-block;
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            border-radius: 50%;
            background: #2563eb;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            vertical-align: top;
            margin-right: 6px;
        }
        .flow-body { display: inline-block; width: 90%; vertical-align: top; }
        .flow-step-title { font-weight: bold; margin-bottom: 2px; }

        .timeline { margin: 8px 0; }
        .timeline-table { width: 100%; }
        .timeline-num {
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            border-radius: 50%;
            background: #2563eb;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .timeline-step-title { font-size: 9px; font-weight: bold; margin-bottom: 4px; color: #475569; }
        .timeline-step-body {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px;
            background: #fff;
        }
        .timeline-arrow { color: #93c5fd; text-align: center; font-size: 14px; vertical-align: middle; }

        .footer {
            position: fixed;
            bottom: -28px;
            left: 0;
            right: 0;
            font-size: 8.5px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
        .footer-left { float: left; }
        .footer-right { float: right; }
        .pagenum:before { content: counter(page); }
        .pagecount:before { content: counter(pages); }
    </style>
</head>
<body>
    <div class="footer">
        <span class="footer-left">Probusiness · Manual de usuario · {{ $generatedAt }}</span>
        <span class="footer-right">Pág. <span class="pagenum"></span> / <span class="pagecount"></span></span>
    </div>

    <div class="cover">
        <div class="cover-brand">Probusiness Intranet</div>
        @if(!empty($logoDataUri))
            <img class="cover-logo" src="{{ $logoDataUri }}" alt="Probusiness">
        @endif
        <h1>{{ $title }}</h1>
        <div class="cover-line"></div>
        <div class="sub">{{ $subtitle }}</div>
        <div class="cover-meta">
            Documento generado automáticamente desde el CMS<br>
            {{ $generatedAt }} (hora Lima)
            @if(($mode ?? '') === 'role')
                · Uso interno
            @else
                · Compilación global
            @endif
        </div>
    </div>

    @php
        $hasToc = false;
        foreach ($roles as $rm) {
            if (!empty($rm['toc'])) { $hasToc = true; break; }
        }
    @endphp

    @if($hasToc)
        <div class="toc-page">
            <h2>Índice</h2>
            @foreach($roles as $roleManual)
                @if(count($roles) > 1)
                    <div class="toc-item-title" style="margin: 12px 0 6px;">
                        Rol: {{ $roleManual['role']['nombre'] ?? $roleManual['role']['slug'] }}
                    </div>
                @endif
                @foreach(($roleManual['toc'] ?? []) as $i => $item)
                    <div class="toc-item">
                        <div class="toc-item-title">{{ $i + 1 }}. {{ $item['title'] }}</div>
                        @foreach(($item['children'] ?? []) as $child)
                            <div class="toc-child">• {{ $child }}</div>
                        @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>
    @endif

    @foreach($roles as $ri => $roleManual)
        <div class="role-block {{ $ri === 0 ? 'first' : '' }}">
            <div class="role-heading">Rol: {{ $roleManual['role']['nombre'] ?? $roleManual['role']['slug'] }}</div>
            @if(!empty($roleManual['role']['meta']['descripcion'] ?? null))
                <div class="role-desc">{{ $roleManual['role']['meta']['descripcion'] }}</div>
            @endif

            @forelse($roleManual['chapters'] as $chapter)
                <div class="chapter">
                    {!! $chapter['html'] !!}
                </div>
            @empty
                <p class="muted">Aún no hay capítulos para este rol.</p>
            @endforelse
        </div>
    @endforeach
</body>
</html>
