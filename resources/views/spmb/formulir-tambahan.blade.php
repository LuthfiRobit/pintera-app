{{-- resources/views/spmb/formulir-tambahan.blade.php --}}
<x-layouts.portal-wizard title="Formulir Tambahan" current="formulir-tambahan" :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal">
    <div class="rounded-2xl border border-gray-200 bg-white p-6" x-data="formulirTambahanForm()">
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-portal-50 text-portal-500">
                <x-icon name="quiz" class="h-4 w-4" />
            </span>
            <h2 class="text-[15.5px] font-bold text-gray-900">Formulir Tambahan</h2>
        </div>
        <div class="my-4 h-px bg-gray-200"></div>

        <form method="POST" action="{{ route('portal.wizard.formulir-tambahan.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            @forelse ($fieldRows as $baris)
                <div class="grid gap-3 max-[480px]:grid-cols-1 {{ count($baris) === 2 ? 'grid-cols-2' : 'grid-cols-1' }}">
                    @foreach ($baris as $field)
                        @include('spmb.partials.formulir-tambahan-field', ['field' => $field])
                    @endforeach
                </div>
            @empty
                <p class="text-[13px] text-gray-500">Tidak ada formulir tambahan untuk jalur ini.</p>
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
