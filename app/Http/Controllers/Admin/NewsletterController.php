<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNewsletterJob;
use App\Models\Newsletter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NewsletterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Newsletter::with('sender:id,name')->latest()->paginate((int) $request->query('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            'audience' => ['required', Rule::in(['all', 'recipients', 'admins'])],
        ]);

        $newsletter = Newsletter::create([
            ...$validated,
            'sent_by' => $request->user()->id,
        ]);

        SendNewsletterJob::dispatch($newsletter->id);

        activity()->performedOn($newsletter)->causedBy($request->user())->log('newsletter_queued');

        return response()->json([
            'message' => 'Newsletter queued for sending.',
            'newsletter' => $newsletter,
        ], 201);
    }

    public function show(Newsletter $newsletter): JsonResponse
    {
        return response()->json($newsletter->load('sender:id,name'));
    }
}
