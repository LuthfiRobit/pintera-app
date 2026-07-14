<x-spmb-public-layout :lembaga="$lembaga" title="Masukkan Kode" :langkah="1">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Masukkan Kode Verifikasi</h2>
        <p class="mt-1 text-sm text-slate">Kode 6 digit sudah dikirim ke email Anda. Berlaku 10 menit.</p>

        <form
            method="POST"
            action="{{ route('spmb.verifikasi-otp.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}"
            class="mt-5 space-y-4"
            x-data="otpInput()"
            @submit="$refs.kodeTersembunyi.value = kode"
        >
            @csrf
            <div>
                <x-input-label value="Kode Verifikasi" />
                <div class="mt-1.5 flex justify-between gap-2">
                    @for ($i = 0; $i < 6; $i++)
                        <input
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            x-ref="kotak{{ $i }}"
                            :value="digit[{{ $i }}]"
                            @input="isiKotak({{ $i }}, $event)"
                            @keydown="tekanBackspace({{ $i }}, $event)"
                            @paste.prevent="tempel($event)"
                            class="h-14 w-12 rounded-xl border-slate/25 text-center font-mono text-xl text-ink shadow-sm focus:border-spmb-accent focus:ring-spmb-accent"
                            @if ($i === 0) autofocus @endif
                        >
                    @endfor
                </div>
                <input type="hidden" name="kode_otp" x-ref="kodeTersembunyi">
                <x-input-error :messages="$errors->get('kode_otp')" class="mt-1.5" />
            </div>
            <x-spmb-primary-button>Verifikasi</x-spmb-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
