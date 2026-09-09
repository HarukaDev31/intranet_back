<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 42px 36px 50px 36px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.45;
        }

        /* —— Portada —— */
        .cover {
            text-align: center;
            padding-top: 90px;
            page-break-after: always;
        }
        .cover-brand {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 18px;
        }
        .cover-logo {
            height: 48px;
            margin-bottom: 20px;
        }
        .cover h1 {
            font-size: 22px;
            color: #0f172a;
            margin: 0 0 10px;
        }
        .cover-line {
            width: 56px;
            height: 3px;
            background: #E8672C;
            margin: 12px auto 16px;
        }
        .cover-sub {
            font-size: 12px;
            color: #475569;
            margin: 0 40px 20px;
        }
        .cover-meta {
            margin: 28px 60px 0;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            font-size: 9px;
            color: #64748b;
            text-align: center;
        }

        /* —— Índice —— */
        .toc-page {
            page-break-after: always;
        }
        .section-title {
            font-size: 14px;
            color: #1F2A44;
            margin: 0 0 12px;
            padding-bottom: 5px;
            border-bottom: 2px solid #E8672C;
        }
        .toc-item { margin: 0 0 8px; }
        .toc-item-title { font-size: 11px; font-weight: bold; color: #0f172a; }
        .toc-child { margin: 2px 0 0 12px; font-size: 9.5px; color: #475569; }

        /* —— Contenido —— */
        .role-heading {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 4px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e2e8f0;
        }
        .role-desc { color: #64748b; margin: 0 0 12px; font-size: 9.5px; }

        .page-card {
            border: 1px solid #e2e8f0;
            margin: 0 0 14px;
        }
        .page-card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 10px;
        }
        .page-card-title { font-size: 12px; font-weight: bold; color: #0f172a; }
        .page-card-desc { font-size: 9px; color: #64748b; margin-top: 2px; }
        .page-card-body { padding: 10px; }

        .grupo { margin: 0 0 12px; }
        .grupo-title { font-size: 11px; font-weight: bold; color: #0f172a; }
        .grupo-clave {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 8.5px;
            color: #64748b;
            margin-top: 1px;
        }
        .grupo-sub { font-size: 9px; color: #64748b; margin-top: 1px; }
        .grupo-children {
            margin-top: 6px;
            padding-left: 8px;
            border-left: 2px solid #e2e8f0;
        }
        .grupo-nested { margin-top: 8px; }

        .widget { margin: 0 0 10px; }
        .widget-title { font-size: 10px; font-weight: bold; color: #0f172a; margin-bottom: 3px; }
        .title-link { color: #2563eb; text-decoration: underline; }
        .media-subtitle {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
            margin: 0 0 8px;
            line-height: 1.35;
        }
        .muted { color: #64748b; font-size: 9px; margin-bottom: 4px; }
        .texto { font-size: 10px; color: #334155; }

        .callout {
            border-left: 4px solid #9A5B00;
            background: #FDF3E3;
            padding: 7px 9px;
            margin: 4px 0 8px;
            color: #9A5B00;
        }
        .callout-warning { border-left-color: #9A5B00; background: #FDF3E3; }
        .callout-danger { border-left-color: #dc2626; background: #fef2f2; color: #991b1b; }
        .callout-info, .callout-note { border-left-color: #2563EB; background: #EAF2FF; color: #1E3A8A; }
        .callout-title { font-weight: bold; margin-bottom: 2px; }

        .qa { margin: 0 0 10px; }
        .qa-q { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #6B7280; margin-bottom: 2px; }
        .qa-a { font-size: 10px; color: #1A1A1A; }
        .result-box {
            background: #EAF7EF;
            border: 1px solid #BFE6CE;
            padding: 8px 10px;
            margin: 6px 0 10px;
            color: #1B7A4B;
            font-size: 10px;
        }
        .steps-group {
            background: #F2F3F5;
            padding: 8px 10px;
            margin: 4px 0 10px;
        }
        .steps-title { font-size: 10px; font-weight: bold; color: #E8672C; margin-bottom: 4px; }
        .steps-ol { margin: 0; padding-left: 16px; }
        .breadcrumb { font-size: 8px; color: #6B7280; margin-bottom: 4px; }
        .articulo-title { font-size: 13px; font-weight: bold; color: #1F2A44; margin-bottom: 4px; }
        .tagrow { margin: 0 0 6px; }
        .tag {
            background: #F2F3F5;
            color: #33415C;
            padding: 1px 6px;
            font-size: 7.5px;
            font-weight: bold;
        }
        .grupo-articulo { border: 1px solid #E5E7EB; padding: 8px; }

        /* Chips vía <table>: DomPDF estira inline-block con fondo a toda la hoja */
        .chip-row, .tabs-table {
            border-collapse: separate;
            border-spacing: 4px 0;
            margin: 2px 0 8px;
        }
        .btn {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
            padding: 3px 8px;
            font-size: 8.5px;
            white-space: nowrap;
            vertical-align: middle;
        }
        .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
        }

        .tab {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 3px 10px;
            font-size: 8.5px;
            color: #475569;
            white-space: nowrap;
            vertical-align: middle;
        }
        .tab-active {
            background: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
            font-weight: bold;
        }

        .filters-table { margin: 2px 0 6px; border-collapse: separate; border-spacing: 6px 4px; }
        .filter-field { width: 33%; vertical-align: top; }
        .filter-label { font-size: 8px; color: #64748b; margin-bottom: 1px; }
        .filter-control {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            background: #ffffff;
            font-size: 9px;
            color: #334155;
        }

        .data-table {
            border-collapse: collapse;
            width: 100%;
            font-size: 8px;
            margin: 4px 0 8px;
        }
        .data-table th {
            background: #1F2A44;
            color: #ffffff;
            text-align: left;
            padding: 4px 5px;
            border: 1px solid #1F2A44;
            font-weight: bold;
        }
        .data-table td {
            padding: 4px 5px;
            border: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: top;
        }
        .pill {
            background: #eff6ff;
            color: #1d4ed8;
            padding: 1px 5px;
            font-size: 7.5px;
        }

        .media { margin: 4px 0; text-align: center; }
        .media img {
            max-width: 70%;
            max-height: 200px;
            width: auto;
            height: auto;
            border: 1px solid #e2e8f0;
        }
        .media-caption { font-size: 8.5px; color: #64748b; text-align: center; margin-top: 3px; }
        .media-placeholder {
            border: 1px dashed #cbd5e1;
            padding: 14px;
            color: #94a3b8;
            text-align: center;
        }

        .embed-box, .card-box, .modal-box {
            border: 1px solid #e2e8f0;
            padding: 7px 9px;
            background: #ffffff;
            margin: 3px 0;
        }
        .modal-title { font-weight: bold; margin-bottom: 5px; font-size: 10px; }
        .card-row { margin-bottom: 3px; }
        .card-label { color: #64748b; font-size: 8.5px; }
        .card-value { color: #0f172a; }

        .flow { margin: 4px 0; }
        .flow-step { margin-bottom: 6px; }
        .flow-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .flow-num {
            width: 18px;
            height: 16px;
            line-height: 16px;
            text-align: center;
            background: #2563eb;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            vertical-align: top;
        }
        .flow-body { vertical-align: top; padding-left: 6px; }
        .flow-step-title { font-weight: bold; margin-bottom: 1px; }

        .timeline { margin: 6px 0; }
        .timeline-table { width: 100%; }
        .timeline-num {
            width: 16px;
            height: 16px;
            line-height: 16px;
            text-align: center;
            background: #2563eb;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .timeline-step-title { font-size: 8.5px; font-weight: bold; margin-bottom: 3px; color: #475569; }
        .timeline-step-body {
            border: 1px solid #e2e8f0;
            padding: 5px;
            background: #fff;
        }
        .timeline-arrow { color: #93c5fd; text-align: center; font-size: 12px; vertical-align: middle; }
    </style>
</head>
<body>
    <div class="cover">
        <div class="cover-brand">Probusiness Intranet</div>
        @if(!empty($logoDataUri))
            <img class="cover-logo" src="{{ $logoDataUri }}" alt="Probusiness">
        @endif
        <h1>{{ $title }}</h1>
        <div class="cover-line"></div>
        <div class="cover-sub">{{ $subtitle }}</div>
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
            <div class="section-title">Índice</div>
            @foreach($roles as $roleManual)
                @if(count($roles) > 1)
                    <div class="toc-item-title" style="margin: 10px 0 5px;">
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
        <div class="role-block">
            @if(count($roles) > 1 || ($mode ?? '') === 'global')
                <div class="role-heading">Rol: {{ $roleManual['role']['nombre'] ?? $roleManual['role']['slug'] }}</div>
                @if(!empty($roleManual['role']['meta']['descripcion'] ?? null))
                    <div class="role-desc">{{ $roleManual['role']['meta']['descripcion'] }}</div>
                @endif
            @elseif(!empty($roleManual['role']['meta']['descripcion'] ?? null))
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
