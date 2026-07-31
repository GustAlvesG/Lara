<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use App\Http\Controllers\FtpController;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request)
    {
        return view('profile.edit', [
            'user' => $request->user(),
            
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Define ou troca o PIN de 6 dígitos usado no Kiosk de assinatura.
     *
     * Pede a senha atual porque o PIN vale como assinatura de contrato: sem
     * isso, uma sessão esquecida aberta bastaria para criar um PIN e assinar
     * em nome do dono da conta.
     */
    public function updatePin(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePin', [
            'current_password' => ['required', 'current_password'],
            'pin' => ['required', 'digits:6', 'confirmed'],
        ], [], [
            'current_password' => 'senha atual',
            'pin' => 'PIN',
        ]);

        // O cast 'hashed' no model guarda somente o hash do PIN.
        $request->user()->update(['pin' => $validated['pin']]);

        return back()->with('status', 'pin-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
