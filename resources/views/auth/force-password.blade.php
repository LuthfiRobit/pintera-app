{{-- resources/views/auth/force-password.blade.php --}}
<x-guest-layout>
    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keamanan Akun</p>
    <h1 class="mt-1 font-display text-2xl font-semibold text-ink">Buat password baru</h1>
    <p class="mt-1 text-sm text-slate">Ini pertama kalinya kamu masuk. Ganti password bawaan sebelum melanjutkan.</p>

    <form method="POST" action="{{ route('password.force.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <x-input-label for="password" :value="__('Password Baru')" />
            <x-text-input id="password" class="mt-1.5" type="password" name="password" required autocomplete="new-password" autofocus />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Konfirmasi Password Baru')" />
            <x-text-input id="password_confirmation" class="mt-1.5" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
        </div>

        <x-primary-button class="w-full justify-center">
            {{ __('Simpan Password Baru') }}
        </x-primary-button>
    </form>
</x-guest-layout>
