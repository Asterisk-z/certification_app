<?php

namespace App\Services;

use App\Enums\TemplateStatus;
use App\Models\CertificateTemplate;
use App\Models\TemplateBlock;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateService
{
    public function create(User $user, array $data, ?UploadedFile $background = null): CertificateTemplate
    {
        if ($background) {
            $data = array_merge($data, $this->storeBackground($background));
        }

        $template = CertificateTemplate::create([
            ...$data,
            'user_id' => $user->id,
            'status' => TemplateStatus::Draft,
        ]);

        // Pull DB defaults (bg_width/bg_height) when no background was
        // uploaded, so the seeded block positions are computed correctly.
        $template->refresh();

        $this->seedDefaultBlocks($template);

        return $template->load('blocks');
    }

    public function update(CertificateTemplate $template, array $data, ?UploadedFile $background = null): CertificateTemplate
    {
        if ($background) {
            if ($template->background_image) {
                Storage::disk('public')->delete($template->background_image);
            }
            $data = array_merge($data, $this->storeBackground($background));
        }

        $template->update($data);

        return $template->load('blocks');
    }

    public function duplicate(CertificateTemplate $template): CertificateTemplate
    {
        $copy = $template->replicate(['uuid', 'counter', 'status']);
        $copy->name = $template->name.' (copy)';
        $copy->code = $this->uniqueCode($template->code);
        $copy->status = TemplateStatus::Draft;
        $copy->counter = 0;

        if ($template->background_image && Storage::disk('public')->exists($template->background_image)) {
            $ext = pathinfo($template->background_image, PATHINFO_EXTENSION);
            $newPath = 'templates/'.Str::uuid().'.'.$ext;
            Storage::disk('public')->copy($template->background_image, $newPath);
            $copy->background_image = $newPath;
        }

        $copy->save();

        foreach ($template->blocks as $block) {
            $blockCopy = $block->replicate(['uuid']);
            $blockCopy->certificate_template_id = $copy->id;
            $blockCopy->save();
        }

        return $copy->load('blocks');
    }

    public function addBlock(CertificateTemplate $template, array $data): TemplateBlock
    {
        $data['slug'] = $this->uniqueSlug($template, $data['name']);
        $data['organization_id'] = $template->organization_id;

        return $template->blocks()->create($data);
    }

    /**
     * Persist designer block positions in one shot and flag the template as
     * ready to send.
     */
    public function saveLayout(CertificateTemplate $template, array $blocks): CertificateTemplate
    {
        $own = $template->blocks()->pluck('id', 'uuid');

        foreach ($blocks as $entry) {
            $id = $own[$entry['uuid']] ?? null;
            if (! $id) {
                continue;
            }

            TemplateBlock::where('id', $id)->update([
                'pos_x' => $entry['pos_x'],
                'pos_y' => $entry['pos_y'],
                'width' => $entry['width'] ?? null,
                'height' => $entry['height'] ?? null,
            ]);
        }

        if ($template->status === TemplateStatus::Draft) {
            $template->update(['status' => TemplateStatus::Ready]);
        }

        activity()->performedOn($template)->causedBy(auth()->user())->log('layout_saved');

        return $template->refresh()->load('blocks');
    }

    private function storeBackground(UploadedFile $file): array
    {
        $path = $file->store('templates', 'public');
        [$width, $height] = getimagesize($file->getRealPath());

        return [
            'background_image' => $path,
            'bg_width' => $width,
            'bg_height' => $height,
        ];
    }

    private function seedDefaultBlocks(CertificateTemplate $template): void
    {
        $centerX = (int) round($template->bg_width / 2) - 150;

        foreach (CertificateTemplate::DEFAULT_BLOCKS as $block) {
            $template->blocks()->create([
                'organization_id' => $template->organization_id,
                'name' => $block['name'],
                'slug' => $block['slug'],
                'type' => $block['type'],
                'value' => '{{'.$block['slug'].'}}',
                'is_dynamic' => true,
                'is_default' => true,
                'is_visible' => true,
                'pos_x' => $block['type'] === 'qrcode' ? $template->bg_width - 180 : $centerX,
                'pos_y' => $block['pos_y'],
                'width' => $block['type'] === 'qrcode' ? 120 : CertificateTemplate::DEFAULT_BLOCK_WIDTH,
                'height' => $block['type'] === 'qrcode' ? 120 : null,
                'font_size' => $block['font_size'],
                'text_align' => 'center',
            ]);
        }
    }

    private function uniqueSlug(CertificateTemplate $template, string $name): string
    {
        $base = Str::slug($name, '_');
        $slug = $base;
        $i = 2;

        while ($template->blocks()->where('slug', $slug)->exists()) {
            $slug = $base.'_'.$i++;
        }

        return $slug;
    }

    private function uniqueCode(string $code): string
    {
        $base = $code.'-COPY';
        $candidate = $base;
        $i = 2;

        while (CertificateTemplate::withTrashed()->where('code', $candidate)->exists()) {
            $candidate = $base.$i++;
        }

        return $candidate;
    }
}
