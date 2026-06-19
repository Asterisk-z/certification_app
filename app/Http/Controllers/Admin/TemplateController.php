<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FiltersByOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\TemplateRequest;
use App\Models\CertificateTemplate;
use App\Services\OrganizationLimitService;
use App\Services\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    use FiltersByOrganization;

    public function __construct(private readonly TemplateService $templates) {}

    public function index(Request $request): JsonResponse
    {
        $query = CertificateTemplate::query()
            ->with('organization:id,uuid,name')
            ->withCount(['blocks', 'certificates'])
            ->latest();

        if ($search = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $this->applyOrganizationFilter($query, $request);

        return response()->json($query->paginate((int) $request->query('per_page', 12)));
    }

    public function store(TemplateRequest $request, OrganizationLimitService $limits): JsonResponse
    {
        $limits->assertCanCreate($request->user(), 'templates');

        $template = $this->templates->create(
            $request->user(),
            $request->safe()->except('background'),
            $request->file('background')
        );

        return response()->json($template, 201);
    }

    public function show(CertificateTemplate $template): JsonResponse
    {
        return response()->json($template->load('blocks')->loadCount('certificates'));
    }

    public function update(TemplateRequest $request, CertificateTemplate $template): JsonResponse
    {
        $template = $this->templates->update(
            $template,
            $request->safe()->except('background'),
            $request->file('background')
        );

        return response()->json($template);
    }

    public function destroy(CertificateTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(['message' => 'Template deleted.']);
    }

    public function duplicate(Request $request, CertificateTemplate $template, OrganizationLimitService $limits): JsonResponse
    {
        $limits->assertCanCreate($request->user(), 'templates');

        return response()->json($this->templates->duplicate($template), 201);
    }
}
