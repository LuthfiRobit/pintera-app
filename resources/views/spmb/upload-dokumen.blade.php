<x-spmb-public-layout :lembaga="$lembaga" title="Upload Dokumen">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Upload Dokumen</h2>

        <form method="POST" action="{{ route('spmb.dokumen.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
            @csrf

            @forelse ($syaratList as $syarat)
                <div class="rounded-xl border-2 border-dashed border-slate/30 p-4">
                    <x-input-label :value="$syarat->nama_dokumen . ($syarat->wajib ? ' *' : ' (opsional)')" />
                    <p class="mt-1 text-xs text-slate">PDF/JPG/PNG, maks 2MB</p>
                    <input type="file" name="dokumen[{{ $syarat->id }}]" class="mt-2 block w-full text-sm text-slate" @required($syarat->wajib) />
                    <x-input-error :messages="$errors->get('dokumen.' . $syarat->id)" class="mt-1.5" />
                </div>
            @empty
                <p class="text-sm text-slate">Tidak ada dokumen yang perlu diupload untuk jalur ini.</p>
            @endforelse

            <x-primary-button>Lanjut ke Review</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
