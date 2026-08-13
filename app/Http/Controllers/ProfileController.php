<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\UserNotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
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
     * Update the user's Finance notification channel preferences.
     */
    public function updateNotificationPreference(Request $request): RedirectResponse
    {
        $request->validate([
            'channel_wa' => ['sometimes', 'boolean'],
            'channel_email' => ['sometimes', 'boolean'],
        ]);

        UserNotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => 'finance'],
            [
                'channel_wa' => $request->boolean('channel_wa'),
                'channel_email' => $request->boolean('channel_email'),
            ]
        );

        return Redirect::route('profile.edit')->with('status', 'notification-preference-updated');
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
