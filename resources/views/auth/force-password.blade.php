{{-- resources/views/auth/force-password.blade.php --}}
<x-guest-layout>
    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keamanan Akun</p>
    <h1 class="mt-1 font-display text-2xl font-semibold text-ink">Buat password baru</h1>
    <p class="mt-1 text-sm text-slate">Ini pertama kalinya kamu masuk. Ganti password bawaan sebelum melanjutkan.</p>

    <form method="POST" action="{{ route('password.force.update') }}" class="mt-8 space-y-6">
        @csrf
        @method('PUT')

        <div class="space-y-1.5">
            <x-input-label for="password" :value="__('Password Baru')" />
            <div x-data="{ show: false }" class="relative">
                <x-text-input id="password" class="pr-10" type="password" 
                                x-bind:type="show ? 'text' : 'password'"
                                name="password" required autocomplete="new-password" autofocus placeholder="Masukkan password baru" />
                <button type="button" @click="show = !show" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                    <x-icon name="visibility_off" x-show="show" style="display: none;" class="h-5 w-5" />
                    <x-icon name="visibility" x-show="!show" class="h-5 w-5" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="space-y-1.5">
            <x-input-label for="password_confirmation" :value="__('Konfirmasi Password Baru')" />
            <div x-data="{ show: false }" class="relative">
                <x-text-input id="password_confirmation" class="pr-10" type="password" 
                                x-bind:type="show ? 'text' : 'password'"
                                name="password_confirmation" required autocomplete="new-password" placeholder="Ulangi password baru" />
                <button type="button" @click="show = !show" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                    <x-icon name="visibility_off" x-show="show" style="display: none;" class="h-5 w-5" />
                    <x-icon name="visibility" x-show="!show" class="h-5 w-5" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full justify-center py-3 text-base shadow-elevated">
                Simpan Password Baru
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
