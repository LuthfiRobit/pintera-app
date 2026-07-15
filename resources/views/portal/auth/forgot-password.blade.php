{{-- resources/views/portal/auth/forgot-password.blade.php --}}
<x-spmb-public-layout title="Lupa Password">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Lupa Password</h2>
        <p class="mt-1 text-sm text-slate">Masukkan email Anda, kami kirimkan tautan untuk mengatur ulang password.</p>

        @if (session('status'))
            <p class="mt-2 text-sm font-medium text-signal-green">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('portal.password.email') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Email" />
                <x-spmb-text-input type="email" name="email" class="mt-1.5" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <x-spmb-primary-button class="w-full justify-center">Kirim Tautan Reset</x-spmb-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
