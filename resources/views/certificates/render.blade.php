<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $certificate->certificate_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: {{ $template->bg_width }}px; height: {{ $template->bg_height }}px; }
        body { position: relative; font-family: Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .bg { position: absolute; inset: 0; width: 100%; height: 100%; }
        .block { position: absolute; overflow: hidden; line-height: 1.2; }
        .block img, .block svg { width: 100%; height: 100%; object-fit: contain; }
    </style>
</head>
<body>
    @if ($backgroundData)
        <img class="bg" src="{{ $backgroundData }}" alt="">
    @endif

    @foreach ($blocks as $block)
        @php
            $style = sprintf(
                'left:%spx;top:%spx;width:%spx;%s',
                $block->pos_x,
                $block->pos_y,
                $block->width ?? 300,
                $block->height ? "height:{$block->height}px;" : ''
            );
        @endphp

        @if ($block->type->value === 'qrcode')
            <div class="block" style="{{ $style }}">
                <img src="data:image/svg+xml;base64,{{ $qrSvg }}" alt="QR code">
            </div>
        @elseif ($block->type->isImageLike())
            @php
                $imageData = null;
                if ($block->value && \Illuminate\Support\Facades\Storage::disk('public')->exists($block->value)) {
                    $imageData = 'data:'.\Illuminate\Support\Facades\Storage::disk('public')->mimeType($block->value)
                        .';base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($block->value));
                }
            @endphp
            <div class="block" style="{{ $style }}">
                @if ($imageData)<img src="{{ $imageData }}" alt="">@endif
            </div>
        @else
            <div
                class="block"
                style="{{ $style }}font-family:'{{ $block->font_family }}',sans-serif;font-size:{{ $block->font_size }}px;color:{{ $block->font_color }};font-weight:{{ $block->font_weight }};text-align:{{ $block->text_align }};"
            >{{ $block->resolved_text }}</div>
        @endif
    @endforeach
</body>
</html>
