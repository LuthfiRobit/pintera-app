<x-spmb-public-layout :lembaga="$lembaga" title="Cek Status">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Cek Status Pendaftaran</h2>

        <form method="POST" action="{{ route('spmb.status.show', ['lembagaSlug' => $lembaga->slug]) }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Kode Pendaftaran" />
                <x-text-input type="text" name="kode_pendaftaran" value="{{ old('kode_pendaftaran') }}" class="mt-1.5 font-mono" required />
                <x-input-error :messages="$errors->get('kode_pendaftaran')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Email" />
                <x-text-input type="email" name="email" value="{{ old('email') }}" class="mt-1.5" required />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <x-primary-button>Cek Status</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
