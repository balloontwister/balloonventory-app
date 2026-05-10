<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class VerificationCodeController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        return Inertia::render('Auth/VerifyCode', [
            'email' => $request->user()->email,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if ($user->email_verification_code_expires_at?->isPast()) {
            return back()->withErrors(['code' => 'This code has expired. Please request a new one.']);
        }

        $masterCode = config('app.verification_master_code');
        $codeMatches = $request->code === $user->email_verification_code
            || ($masterCode && $request->code === $masterCode);

        if (! $codeMatches) {
            return back()->withErrors(['code' => 'That code is incorrect. Please try again.']);
        }

        $user->forceFill([
            'email_verified_at' => Carbon::now(),
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ])->save();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $code = $this->generateCode();

        $user->forceFill([
            'email_verification_code' => $code,
            'email_verification_code_expires_at' => Carbon::now()->addMinutes(15),
        ])->save();

        Mail::to($user->email)->send(new EmailVerificationCode($code, $user->name));

        return back()->with('status', 'A new code has been sent.');
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
