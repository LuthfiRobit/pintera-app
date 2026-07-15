{{-- resources/views/portal/auth/login.blade.php --}}
<x-spmb-public-layout title="Masuk">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Masuk ke Akun Pendaftar</h2>

        @if (session('status'))
            <p class="mt-2 text-sm font-medium text-signal-green">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('portal.login') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Email" />
                <x-spmb-text-input type="email" name="email" class="mt-1.5" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Password" />
                <x-spmb-text-input type="password" name="password" class="mt-1.5" required />
            </div>
            <div class="flex items-center justify-between">
                <label class="inline-flex items-center text-sm text-slate">
                    <input type="checkbox" name="remember" class="rounded border-slate/25 text-spmb-accent shadow-sm focus:ring-spmb-accent">
                    <span class="ms-2">Ingat saya</span>
                </label>
                <a href="{{ route('portal.password.request') }}" class="text-sm text-spmb-accent hover:underline">Lupa password?</a>
            </div>
            <x-spmb-primary-button class="w-full justify-center">Masuk</x-spmb-primary-button>
        </form>

        <p class="mt-5 text-center text-sm text-slate">
            Belum punya akun? <a href="{{ route('portal.register') }}" class="font-semibold text-spmb-accent hover:underline">Daftar</a>
        </p>
    </x-panel>
</x-spmb-public-layout>
