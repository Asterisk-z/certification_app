<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recipient;
use App\Services\RecipientInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipientInviteController extends Controller
{
    public function store(Request $request, Recipient $recipient, RecipientInviteService $invites): JsonResponse
    {
        $invites->send($recipient, $request->user());

        return response()->json(['message' => "Invite sent to {$recipient->email}."]);
    }
}
