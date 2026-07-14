<x-spmb-public-layout :lembaga="$lembaga" title="Upload Dokumen" :langkah="4">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Upload Dokumen</h2>

        <form method="POST" action="{{ route('spmb.dokumen.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
            @csrf

            @forelse ($syaratList as $syarat)
                <div
                    x-data="{ namaFile: null }"
                    class="rounded-xl border-2 border-dashed p-4 transition"
                    :class="namaFile ? 'border-spmb-accent/40 bg-spmb-tint' : 'border-slate/30'"
                >
                    <x-input-label :value="$syarat->nama_dokumen . ($syarat->wajib ? ' *' : ' (opsional)')" />
                    <p class="mt-1 text-xs text-slate">PDF/JPG/PNG, maks 2MB</p>
                    <input
                        type="file"
                        name="dokumen[{{ $syarat->id }}]"
                        x-ref="input"
                        @change="namaFile = $event.target.files[0]?.name ?? null"
                        class="mt-2 block w-full text-sm text-slate"
                        @required($syarat->wajib)
                    />
                    <p class="mt-2 flex items-center gap-2 text-xs" x-show="namaFile" x-cloak>
                        <span class="font-medium text-ink" x-text="namaFile"></span>
                        <button
                            type="button"
                            class="font-medium text-signal-red hover:text-signal-red/80"
                            @click="$refs.input.value = ''; namaFile = null"
                        >Hapus</button>
                    </p>
                    <x-input-error :messages="$errors->get('dokumen.' . $syarat->id)" class="mt-1.5" />
                </div>
            @empty
                <p class="text-sm text-slate">Tidak ada dokumen yang perlu diupload untuk jalur ini.</p>
            @endforelse

            <x-spmb-primary-button>Lanjut ke Review</x-spmb-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
