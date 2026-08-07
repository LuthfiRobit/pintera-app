<x-guest-layout>
    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Masuk</p>
    <h1 class="mt-1 font-display text-2xl font-semibold text-ink">Selamat datang kembali</h1>
    <p class="mt-1 text-sm text-slate">Masuk dengan akun yang terdaftar di sistem administrasi yayasan.</p>

    <x-auth-session-status class="mt-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-6">
        @csrf

        <div class="space-y-1.5">
            <x-input-label for="email" :value="__('Email atau Username')" />
            <x-text-input id="email" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="Masukkan email atau username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="space-y-1.5">
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Kata Sandi')" />
                @if (Route::has('password.request'))
                    <a class="text-xs font-semibold text-brand-600 hover:text-brand-500 transition-colors" href="{{ route('password.request') }}">
                        Lupa Password?
                    </a>
                @endif
            </div>
            <div x-data="{ show: false }" class="relative">
                <x-text-input id="password" class="pr-10"
                                x-bind:type="show ? 'text' : 'password'"
                                name="password"
                                placeholder="Masukkan kata sandi"
                                required autocomplete="current-password" />
                <button type="button" @click="show = !show" tabindex="-1" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                    <x-icon x-bind:name="show ? 'visibility_off' : 'visibility'" class="h-5 w-5" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer group">
                <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500/20 transition-colors cursor-pointer" name="remember">
                <span class="text-sm font-medium text-gray-600 group-hover:text-gray-900 transition-colors">Ingat Saya</span>
            </label>
        </div>

        <x-primary-button class="w-full justify-center py-3 text-base shadow-elevated">
            Masuk ke Sistem
        </x-primary-button>
    </form>
</x-guest-layout>
