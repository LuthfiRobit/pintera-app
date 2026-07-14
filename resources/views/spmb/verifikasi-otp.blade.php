<x-spmb-public-layout :lembaga="$lembaga" title="Masukkan Kode">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Masukkan Kode Verifikasi</h2>
        <p class="mt-1 text-sm text-slate">Kode 6 digit sudah dikirim ke email Anda. Berlaku 10 menit.</p>

        <form method="POST" action="{{ route('spmb.verifikasi-otp.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Kode Verifikasi" />
                <x-text-input
                    type="text"
                    name="kode_otp"
                    inputmode="numeric"
                    maxlength="6"
                    class="mt-1.5 text-center font-mono text-xl tracking-[0.5em]"
                    required
                    autofocus
                />
                <x-input-error :messages="$errors->get('kode_otp')" class="mt-1.5" />
            </div>
            <x-primary-button>Verifikasi</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
