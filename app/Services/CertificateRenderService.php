<?php

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\Browsershot\Browsershot;

class CertificateRenderService
{
    public function __construct(private readonly PlaceholderResolver $resolver) {}

    /**
     * Render the certificate to HTML (shared by PDF generation and the
     * public certificate view).
     */
    public function html(Certificate $certificate): string
    {
        $certificate->loadMissing('template.blocks', 'recipient');
        $template = $certificate->template;
        $values = $this->resolver->resolve($certificate);

        $qrSvg = base64_encode(
            QrCode::format('svg')->size(600)->margin(1)->generate($this->verifyUrl($certificate))
        );

        $blocks = $template->blocks
            ->where('is_visible', true)
            ->values()
            ->map(function ($block) use ($values) {
                $block->resolved_text = $block->type->value === 'text'
                    ? $this->resolver->interpolate($block->value, $values)
                    : null;

                return $block;
            });

        return view('certificates.render', [
            'certificate' => $certificate,
            'template' => $template,
            'blocks' => $blocks,
            'qrSvg' => $qrSvg,
            'backgroundData' => $this->backgroundDataUri($template->background_image),
        ])->render();
    }

    /**
     * Generate (or return the existing) PDF and store it on the local disk.
     * Returns the relative path on the "local" disk.
     */
    public function pdf(Certificate $certificate, bool $force = false): string
    {
        if (! $force && $certificate->pdf_path && Storage::disk('local')->exists($certificate->pdf_path)) {
            return $certificate->pdf_path;
        }

        $template = $certificate->template;
        $path = "certificates/{$certificate->uuid}.pdf";
        $absolute = Storage::disk('local')->path($path);
        Storage::disk('local')->makeDirectory('certificates');

        $shot = Browsershot::html($this->html($certificate))
            ->noSandbox()
            // Containers ship a tiny /dev/shm; without this Chromium can crash.
            ->addChromiumArguments(['disable-dev-shm-usage'])
            ->showBackground()
            ->paperSize($this->pxToMm($template->bg_width), $this->pxToMm($template->bg_height))
            ->margins(0, 0, 0, 0)
            ->timeout(120);

        if ($chrome = config('services.chrome.path')) {
            $shot->setChromePath($chrome);
        }

        $shot->savePdf($absolute);

        $certificate->forceFill(['pdf_path' => $path])->save();

        return $path;
    }

    public function verifyUrl(Certificate $certificate): string
    {
        return url('/?number='.urlencode($certificate->certificate_number));
    }

    /**
     * Inline the background as a data URI so Browsershot does not need
     * network access to the app host.
     */
    private function backgroundDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path);
        $data = base64_encode(Storage::disk('public')->get($path));

        return "data:{$mime};base64,{$data}";
    }

    private function pxToMm(int $px): float
    {
        return round($px * 25.4 / 96, 2);
    }
}
