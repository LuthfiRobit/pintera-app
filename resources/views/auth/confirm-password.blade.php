<x-guest-layout>
    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Konfirmasi</p>
    <h1 class="mt-1 font-display text-2xl font-semibold text-ink">Konfirmasi password</h1>
    <p class="mt-2 text-sm text-slate">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1.5"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <x-primary-button class="w-full justify-center">
            {{ __('Confirm') }}
        </x-primary-button>
    </form>
</x-guest-layout>
