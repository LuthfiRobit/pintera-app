<x-spmb-public-layout :lembaga="$lembaga" title="Verifikasi Email" :langkah="1">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Verifikasi Email</h2>
        <p class="mt-1 text-sm text-slate">Masukkan email aktif Anda. Kami akan kirim kode verifikasi 6 digit.</p>

        @error('sesi')
            <div class="mt-4 rounded-xl border border-signal-amber/30 bg-signal-amber/5 p-4 text-sm text-signal-amber">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('spmb.mulai.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Email" />
                <x-spmb-text-input type="email" name="email" value="{{ old('email') }}" class="mt-1.5" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <x-spmb-primary-button>Kirim Kode</x-spmb-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
