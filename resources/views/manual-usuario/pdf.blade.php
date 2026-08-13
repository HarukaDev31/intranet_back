<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.45;
        }
        h1 { font-size: 20px; margin: 0 0 6px; color: #0f172a; }
        h2 { font-size: 15px; margin: 22px 0 8px; color: #0f172a; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        h3 { font-size: 13px; margin: 14px 0 6px; color: #111827; }
        h4 { font-size: 12px; margin: 10px 0 4px; }
        p, li { margin: 0 0 6px; }
        ul { margin: 0 0 8px 18px; padding: 0; }
        .cover { text-align: center; margin-top: 80px; margin-bottom: 40px; }
        .cover .sub { color: #64748b; margin-top: 8px; }
        .meta { color: #64748b; font-size: 10px; margin-bottom: 18px; }
        .role-block { page-break-before: always; }
        .role-block:first-of-type { page-break-before: auto; }
        .chapter { margin-bottom: 16px; }
        img { max-width: 100%; height: auto; margin: 8px 0; border: 1px solid #e5e7eb; }
        code { font-size: 10px; }
        .toc li { margin-bottom: 3px; }
    </style>
</head>
<body>
    <div class="cover">
        <h1>{{ $title }}</h1>
        <div class="sub">{{ $subtitle }}</div>
        <div class="meta">Generado: {{ $generatedAt }} (hora Lima)</div>
    </div>

    @if(!empty($globalChapters))
        <h2>Secciones generales</h2>
        @foreach($globalChapters as $chapter)
            <div class="chapter">
                {!! $chapter['html'] !!}
            </div>
        @endforeach
    @endif

    @foreach($roles as $roleManual)
        <div class="role-block">
            <h2>Rol: {{ $roleManual['role']['nombre'] ?? $roleManual['role']['slug'] }}</h2>
            @if(!empty($roleManual['role']['meta']['descripcion'] ?? null))
                <p>{{ $roleManual['role']['meta']['descripcion'] }}</p>
            @endif

            @forelse($roleManual['chapters'] as $chapter)
                <div class="chapter">
                    {!! $chapter['html'] !!}
                </div>
            @empty
                <p>Aún no hay capítulos para este rol.</p>
            @endforelse
        </div>
    @endforeach
</body>
</html>
