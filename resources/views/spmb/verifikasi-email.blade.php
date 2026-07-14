<x-spmb-public-layout :lembaga="$lembaga" title="Verifikasi Email">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Verifikasi Email</h2>
        <p class="mt-1 text-sm text-slate">Masukkan email aktif Anda. Kami akan kirim kode verifikasi 6 digit.</p>

        <form method="POST" action="{{ route('spmb.mulai.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Email" />
                <x-text-input type="email" name="email" value="{{ old('email') }}" class="mt-1.5" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <x-primary-button>Kirim Kode</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
