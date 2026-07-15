{{-- resources/views/portal/auth/reset-password.blade.php --}}
<x-spmb-public-layout title="Atur Ulang Password">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Atur Ulang Password</h2>

        <form method="POST" action="{{ route('portal.password.store') }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div>
                <x-input-label value="Email" />
                <x-spmb-text-input type="email" name="email" class="mt-1.5" :value="old('email', $request->email)" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Password Baru" />
                <x-spmb-text-input type="password" name="password" class="mt-1.5" required />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Konfirmasi Password Baru" />
                <x-spmb-text-input type="password" name="password_confirmation" class="mt-1.5" required />
            </div>
            <x-spmb-primary-button class="w-full justify-center">Simpan Password Baru</x-spmb-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
