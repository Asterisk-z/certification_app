<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class InviteController extends Controller
{
    /**
     * Validate a signed invite link and report who it is for.
     * Route is protected by the `signed` middleware.
     */
    public function show(Request $request, Recipient $recipient): JsonResponse
    {
        return response()->json([
            'recipient' => [
                'full_name' => $recipient->full_name,
                'email' => $recipient->email,
                'has_account' => $recipient->user_id !== null && $recipient->user?->password !== null,
            ],
        ]);
    }

    /**
     * Accept the invite: create (or claim) the portal user and set a password.
     */
    public function store(Request $request, Recipient $recipient): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $recipient->user;

        if (! $user) {
            $user = User::withTrashed()->where('email', $recipient->email)->first();
        }

        if ($user && $user->trashed()) {
            $user->restore();
        }

        if (! $user) {
            $user = User::create([
                'name' => $recipient->full_name,
                'email' => $recipient->email,
                'role' => 'recipient',
                'password' => $validated['password'],
            ]);
        } else {
            $user->forceFill(['password' => bcrypt($validated['password'])])->save();
        }

        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

        if ($recipient->user_id !== $user->id) {
            $recipient->user_id = $user->id;
            $recipient->save();
        }

        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        activity()->causedBy($user)->performedOn($recipient)->log('invite_accepted');

        return response()->json(['user' => $user->load('recipient')]);
    }
}
