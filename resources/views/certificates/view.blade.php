<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $certificate->displayName() ?? 'Credential' }} · {{ $certificate->certificate_number }}</title>
    <link rel="icon" type="image/png" sizes="512x512" href="/images/icon.png">
    <link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Follow the theme chosen in the app (same storage key as app.blade.php).
        (function () {
            var mode = localStorage.getItem('theme') || 'system';
            var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (dark) document.documentElement.classList.add('dark');
        })();
    </script>
    <script>
        // Deterrent only: keep the browser's save/copy menu off the credential.
        document.addEventListener('contextmenu', function (event) { event.preventDefault(); });
    </script>
    <style>
        :root { --page: #f1f5f9; --surface: #ffffff; --line: #e2e8f0; --text: #0f172a; --muted: #64748b; --brand: #2563eb; --brand-hover: #1d4ed8; --ghost-hover: #f8fafc; }
        .dark { --page: #020617; --surface: #0f172a; --line: #1e293b; --text: #f1f5f9; --muted: #94a3b8; --ghost-hover: #1e293b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { min-height: 100vh; background: var(--page); color: var(--text); font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        .bar { background: var(--surface); border-bottom: 1px solid var(--line); }
        .bar-inner { max-width: 1040px; margin: 0 auto; padding: 14px 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .name { font-size: 16px; font-weight: 600; line-height: 1.3; }
        .number { margin-top: 2px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; color: var(--muted); }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border-radius: 8px; border: 1px solid var(--line); font-size: 14px; font-weight: 500; color: var(--text); text-decoration: none; white-space: nowrap; }
        .btn:hover { background: var(--ghost-hover); }
        .btn svg { width: 16px; height: 16px; flex: none; }
        .btn-primary { background: var(--brand); border-color: var(--brand); color: #ffffff; }
        .btn-primary:hover { background: var(--brand-hover); border-color: var(--brand-hover); }
        main { max-width: 1040px; margin: 0 auto; padding: 20px 16px 40px; }
        .stage { position: relative; margin: 0 auto; overflow: hidden; background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(15, 23, 42, .12), 0 0 0 1px var(--line); }
        .stage iframe { display: block; border: 0; }
        .stage.rendered iframe { position: absolute; top: 0; left: 0; transform-origin: 0 0; }
        .stage.file iframe { width: 100%; height: calc(100vh - 150px); min-height: 480px; }
    </style>
</head>
<body>
    @php
        $downloadUrl = url('/c/'.$certificate->uuid.'/download');
        $embedUrl = url('/c/'.$certificate->uuid).'?embed=1';
    @endphp

    <header class="bar">
        <div class="bar-inner">
            <div>
                <h1 class="name">{{ $certificate->displayName() ?? 'Credential' }}</h1>
                <p class="number">{{ $certificate->certificate_number }}</p>
            </div>
            <div class="actions">
                <a class="btn btn-primary" href="{{ $downloadUrl }}?format=pdf">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                    Download PDF
                </a>
                {{-- A manually uploaded credential only exists as its PDF. --}}
                @unless ($uploaded)
                    <a class="btn" href="{{ $downloadUrl }}?format=png">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                        Download PNG
                    </a>
                @endunless
            </div>
        </div>
    </header>

    <main>
        @if ($uploaded)
            <div class="stage file">
                <iframe src="{{ $embedUrl }}" title="Credential"></iframe>
            </div>
        @else
            @php
                $width = $certificate->template->bg_width;
                $height = $certificate->template->bg_height;
            @endphp
            {{-- The certificate is laid out at its template's pixel size; scale it to the page width. --}}
            <div class="stage rendered" id="stage" style="max-width: min({{ $width }}px, 100%); aspect-ratio: {{ $width }} / {{ $height }};">
                <iframe id="document" src="{{ $embedUrl }}" title="Credential" width="{{ $width }}" height="{{ $height }}" scrolling="no"></iframe>
            </div>
            <script>
                (function () {
                    var stage = document.getElementById('stage');
                    var frame = document.getElementById('document');
                    var fit = function () {
                        frame.style.transform = 'scale(' + stage.clientWidth / {{ $width }} + ')';
                    };
                    fit();
                    window.addEventListener('resize', fit);
                })();
            </script>
        @endif
    </main>
</body>
</html>
