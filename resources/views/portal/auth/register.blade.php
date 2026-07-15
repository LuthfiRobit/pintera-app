{{-- resources/views/portal/auth/register.blade.php --}}
<x-spmb-public-layout title="Daftar Akun">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Daftar Akun Pendaftar</h2>
        <p class="mt-1 text-sm text-slate">Buat akun untuk memantau semua pendaftaran SPMB Anda di satu tempat.</p>

        <form method="POST" action="{{ route('portal.register') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Nama Lengkap" />
                <x-spmb-text-input type="text" name="nama" class="mt-1.5" :value="old('nama')" required autofocus />
                <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Email" />
                <x-spmb-text-input type="email" name="email" class="mt-1.5" :value="old('email')" required />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Password" />
                <x-spmb-text-input type="password" name="password" class="mt-1.5" required />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Konfirmasi Password" />
                <x-spmb-text-input type="password" name="password_confirmation" class="mt-1.5" required />
            </div>
            <x-spmb-primary-button class="w-full justify-center">Daftar</x-spmb-primary-button>
        </form>

        <p class="mt-5 text-center text-sm text-slate">
            Sudah punya akun? <a href="{{ route('portal.login') }}" class="font-semibold text-spmb-accent hover:underline">Masuk</a>
        </p>
    </x-panel>
</x-spmb-public-layout>
