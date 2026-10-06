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

        if (! $template) {
            throw new \RuntimeException('This credential has no template to render — only its uploaded file is available.');
        }
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

        $this->browsershot($certificate)
            ->paperSize($this->pxToMm($template->bg_width), $this->pxToMm($template->bg_height))
            ->margins(0, 0, 0, 0)
            ->savePdf($absolute);

        $certificate->forceFill(['pdf_path' => $path])->save();

        return $path;
    }

    /**
     * Generate (or return the existing) PNG image of the certificate.
     * Returns the relative path on the "local" disk.
     */
    public function png(Certificate $certificate, bool $force = false): string
    {
        if (! $force && $certificate->png_path && Storage::disk('local')->exists($certificate->png_path)) {
            return $certificate->png_path;
        }

        $template = $certificate->template;
        $path = "certificates/{$certificate->uuid}.png";
        $absolute = Storage::disk('local')->path($path);
        Storage::disk('local')->makeDirectory('certificates');

        $this->browsershot($certificate)
            ->windowSize($template->bg_width, $template->bg_height)
            ->save($absolute);

        $certificate->forceFill(['png_path' => $path])->save();

        return $path;
    }

    /**
     * A Browsershot instance for this certificate's HTML, configured the same
     * way for every output format.
     */
    private function browsershot(Certificate $certificate): Browsershot
    {
        $shot = Browsershot::html($this->html($certificate))
            ->noSandbox()
            // Chrome 132+ only ships the new headless mode; Browsershot still
            // defaults to the removed "shell" mode.
            ->newHeadless()
            // Containers ship a tiny /dev/shm; without this Chromium can crash.
            ->addChromiumArguments(['disable-dev-shm-usage'])
            ->showBackground()
            ->timeout(120)
            // Our wrapper around Browsershot's script caps how long it waits for
            // Chrome to exit (see resources/node/browsershot.cjs).
            ->setBinPath(base_path('resources/node/browsershot.cjs'));

        if ($chrome = config('services.chrome.path')) {
            $shot->setChromePath($chrome);
        }

        return $shot;
    }

    /**
     * Resolve the file to download for a format, rendering on demand.
     *
     * @throws \RuntimeException when the format is unavailable
     */
    public function downloadPath(Certificate $certificate, string $format = 'pdf'): string
    {
        if ($format === 'png') {
            if ($certificate->uploaded_file_path) {
                throw new \RuntimeException('Only the manually uploaded PDF is available for this credential.');
            }

            return $certificate->png_path && Storage::disk('local')->exists($certificate->png_path)
                ? $certificate->png_path
                : $this->png($certificate);
        }

        $path = $certificate->uploaded_file_path ?: $certificate->pdf_path;

        return $path && Storage::disk('local')->exists($path) ? $path : $this->pdf($certificate);
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
