<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Models\Recipient;
use App\Services\CertificateIssueService;
use App\Services\CertificateRenderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function __construct(private readonly CertificateIssueService $issuer) {}

    public function index(Request $request): JsonResponse
    {
        $query = Certificate::query()->with(['recipient:id,uuid,full_name,email', 'template:id,uuid,name,code'])->latest();

        $status = $request->query('status');

        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($templateUuid = $request->query('template')) {
            $query->whereHas('template', fn ($q) => $q->where('uuid', $templateUuid));
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->search($search);
        }

        return response()->json($query->paginate((int) $request->query('per_page', 15)));
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $certificate = Certificate::withTrashed()
            ->where('uuid', $uuid)
            ->with(['recipient', 'template.blocks', 'group', 'renewedFrom:id,uuid,certificate_number'])
            ->firstOrFail();

        return response()->json($certificate);
    }

    /**
     * Send a ready template to a group and/or hand-picked recipients.
     */
    public function send(Request $request, CertificateTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'group_uuid' => ['nullable', 'uuid', 'exists:groups,uuid', 'required_without:recipient_uuids'],
            'recipient_uuids' => ['nullable', 'array', 'required_without:group_uuid'],
            'recipient_uuids.*' => ['uuid'],
            'completion_date' => ['required', 'date'],
            'issue_date' => ['required', 'date'],
            'data' => ['nullable', 'array'],
        ]);

        $group = isset($validated['group_uuid'])
            ? Group::where('uuid', $validated['group_uuid'])->firstOrFail()
            : null;

        $recipients = collect();

        if ($group) {
            $recipients = $recipients->merge($group->recipients);
        }

        if (! empty($validated['recipient_uuids'])) {
            $recipients = $recipients->merge(Recipient::whereIn('uuid', $validated['recipient_uuids'])->get());
        }

        $recipients = $recipients->unique('id')->values();

        if ($recipients->isEmpty()) {
            return response()->json(['message' => 'No recipients to send to.'], 422);
        }

        $certificates = $this->issuer->issue(
            $template,
            $recipients,
            Carbon::parse($validated['completion_date']),
            Carbon::parse($validated['issue_date']),
            $validated['data'] ?? [],
            $group,
        );

        activity()->performedOn($template)->causedBy($request->user())
            ->withProperties(['count' => $certificates->count()])->log('certificates_queued');

        return response()->json([
            'message' => $certificates->count().' certificate(s) queued for sending.',
            'count' => $certificates->count(),
        ], 201);
    }

    /**
     * Manually create a certificate, optionally with a custom number.
     */
    public function storeManual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_uuid' => ['required', 'uuid', 'exists:certificate_templates,uuid'],
            'recipient_uuid' => ['required', 'uuid', 'exists:recipients,uuid'],
            'certificate_number' => ['nullable', 'string', 'max:60', Rule::unique('certificates', 'certificate_number')],
            'completion_date' => ['required', 'date'],
            'issue_date' => ['required', 'date'],
            'data' => ['nullable', 'array'],
            'send_now' => ['boolean'],
        ]);

        $template = CertificateTemplate::where('uuid', $validated['template_uuid'])->firstOrFail();
        $recipient = Recipient::where('uuid', $validated['recipient_uuid'])->firstOrFail();

        $certificate = $this->issuer->createCertificate(
            $template,
            $recipient,
            Carbon::parse($validated['completion_date']),
            Carbon::parse($validated['issue_date']),
            $validated['data'] ?? [],
            null,
            $validated['certificate_number'] ?? null,
        );

        if ($request->boolean('send_now')) {
            $this->issuer->queueSend($certificate);
        }

        activity()->performedOn($certificate)->causedBy($request->user())->log('certificate_created_manually');

        return response()->json($certificate->load('recipient', 'template'), 201);
    }

    public function download(string $uuid): StreamedResponse|JsonResponse
    {
        $certificate = Certificate::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $format = request()->query('format') === 'png' ? 'png' : 'pdf';

        try {
            $path = app(CertificateRenderService::class)->downloadPath($certificate, $format);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'The file is not available yet: '.$e->getMessage()], 422);
        }

        return Storage::disk('local')->download(
            $path,
            $certificate->certificate_number.'.'.$format
        );
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $certificate = Certificate::where('uuid', $uuid)->firstOrFail();
        $certificate->delete();

        activity()->performedOn($certificate)->causedBy($request->user())->log('certificate_deleted');

        return response()->json(['message' => 'Certificate moved to deleted.']);
    }
}
