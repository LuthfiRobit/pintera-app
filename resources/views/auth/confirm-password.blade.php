<x-guest-layout>
    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Konfirmasi</p>
    <h1 class="mt-1 font-display text-2xl font-semibold text-ink">Konfirmasi password</h1>
    <p class="mt-2 text-sm text-slate">
        Ini adalah area aman dalam aplikasi. Harap konfirmasi kata sandi Anda sebelum melanjutkan.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-8 space-y-6">
        @csrf

        <div class="space-y-1.5">
            <x-input-label for="password" :value="__('Kata Sandi')" />
            <div x-data="{ show: false }" class="relative">
                <x-text-input id="password" class="pr-10" type="password"
                                x-bind:type="show ? 'text' : 'password'"
                                name="password"
                                placeholder="Masukkan kata sandi Anda"
                                required autocomplete="current-password" autofocus />
                <button type="button" @click="show = !show" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                    <x-icon name="visibility_off" x-show="show" style="display: none;" class="h-5 w-5" />
                    <x-icon name="visibility" x-show="!show" class="h-5 w-5" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full justify-center py-3 text-base shadow-elevated">
                Konfirmasi
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
