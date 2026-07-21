{{-- resources/views/spmb/upload-dokumen.blade.php --}}
<x-layouts.portal-wizard title="Upload Dokumen" current="dokumen" :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal">
    <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-portal-50 text-portal-500">
                <x-icon name="receipt_long" class="h-4 w-4" />
            </span>
            <h2 class="text-[15.5px] font-bold text-gray-900">Upload Dokumen</h2>
        </div>
        <div class="my-4 h-px bg-gray-200"></div>

        <form method="POST" action="{{ route('portal.wizard.dokumen.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            @forelse ($syaratList as $syarat)
                <div
                    x-data="{ namaFile: null }"
                    class="rounded-xl border-2 border-dashed p-4 transition"
                    :class="namaFile ? 'border-portal-500/40 bg-portal-50' : 'border-gray-200'"
                >
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">{{ $syarat->nama_dokumen }}{{ $syarat->wajib ? ' *' : ' (opsional)' }}</label>
                    <p class="mb-2 text-[11px] text-gray-400">PDF/JPG/PNG, maks 2MB</p>
                    <input
                        type="file"
                        name="dokumen[{{ $syarat->id }}]"
                        x-ref="input"
                        @change="namaFile = $event.target.files[0]?.name ?? null"
                        class="block w-full text-[12.5px] text-gray-500"
                        @required($syarat->wajib)
                    />
                    <p class="mt-2 flex items-center gap-2 text-[12px]" x-show="namaFile" x-cloak>
                        <span class="font-semibold text-gray-900" x-text="namaFile"></span>
                        <button type="button" class="font-semibold text-error-700" @click="$refs.input.value = ''; namaFile = null">Hapus</button>
                    </p>
                    @error('dokumen.' . $syarat->id) <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
            @empty
                <p class="text-[13px] text-gray-500">Tidak ada dokumen yang perlu diupload untuk jalur ini.</p>
            @endforelse

            <div class="flex justify-end border-t border-dashed border-gray-200 pt-5">
                <button type="submit" class="flex items-center justify-center gap-2 rounded-[10px] bg-portal-500 px-6 py-3 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Simpan &amp; Lanjutkan
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </button>
            </div>
        </form>
    </div>
</x-layouts.portal-wizard>
