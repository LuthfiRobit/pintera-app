<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Akun</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">{{ __('Profile') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        <x-panel class="p-6">
            @include('profile.partials.update-profile-information-form')
        </x-panel>

        @if (Auth::user()->orangTua !== null)
            <x-panel class="p-6">
                @include('profile.partials.update-notification-preference-form', ['preference' => Auth::user()->notificationPreference])
            </x-panel>
        @endif

        <x-panel class="p-6">
            @include('profile.partials.update-password-form')
        </x-panel>

        <x-panel class="p-6">
            @include('profile.partials.delete-user-form')
        </x-panel>
    </div>
</x-app-layout>
