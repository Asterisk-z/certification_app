<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        if (! Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            $credentials['remember'] ?? false
        )) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // An organization account can only sign in while its organization is
        // present and active — never establish a session otherwise.
        $user = $request->user();
        if ($user->isOrganization() && ! $user->organization?->isActive()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => ['This organization account is inactive.'],
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        activity()->causedBy($user)->log('logged_in');

        return response()->json(['user' => $user->load('recipient', 'organization')]);
    }

    public function logout(Request $request): JsonResponse
    {
        activity()->causedBy($request->user())->log('logged_out');

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->load('recipient', 'organization')]);
    }
}
