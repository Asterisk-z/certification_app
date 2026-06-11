<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use App\Services\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateDesignerController extends Controller
{
    public function __construct(private readonly TemplateService $templates) {}

    public function saveLayout(Request $request, CertificateTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'blocks' => ['required', 'array'],
            'blocks.*.uuid' => ['required', 'uuid'],
            'blocks.*.pos_x' => ['required', 'numeric', 'min:0', 'max:'.$template->bg_width],
            'blocks.*.pos_y' => ['required', 'numeric', 'min:0', 'max:'.$template->bg_height],
            'blocks.*.width' => ['nullable', 'numeric', 'min:10', 'max:'.$template->bg_width],
            'blocks.*.height' => ['nullable', 'numeric', 'min:10', 'max:'.$template->bg_height],
        ]);

        $template = $this->templates->saveLayout($template, $validated['blocks']);

        return response()->json($template);
    }
}
