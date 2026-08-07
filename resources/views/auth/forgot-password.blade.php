<x-guest-layout>
    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Lupa Password</p>
    <h1 class="mt-1 font-display text-2xl font-semibold text-ink">Atur ulang password</h1>
    <p class="mt-2 text-sm text-slate">
        Tidak masalah. Masukkan alamat email yang terdaftar, dan kami akan mengirimkan tautan untuk mengatur ulang kata sandi Anda.
    </p>

    <x-auth-session-status class="mt-5" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-6">
        @csrf

        <div class="space-y-1.5">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus placeholder="Masukkan alamat email Anda" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full justify-center py-3 text-base shadow-elevated">
                Kirim Link Reset Password
            </x-primary-button>
        </div>
        
        <div class="text-center mt-6">
            <a href="{{ route('login') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-500 transition-colors">
                &larr; Kembali ke halaman Masuk
            </a>
        </div>
    </form>
</x-guest-layout>
