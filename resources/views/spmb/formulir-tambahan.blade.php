{{-- resources/views/spmb/formulir-tambahan.blade.php --}}
<x-layouts.portal-wizard title="Formulir Tambahan" current="formulir-tambahan" :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal">
    <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-portal-50 text-portal-500">
                <x-icon name="quiz" class="h-4 w-4" />
            </span>
            <h2 class="text-[15.5px] font-bold text-gray-900">Formulir Tambahan</h2>
        </div>
        <div class="my-4 h-px bg-gray-200"></div>

        <form method="POST" action="{{ route('portal.wizard.formulir-tambahan.store') }}" class="space-y-4">
            @csrf

            @forelse ($fieldList as $field)
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">{{ $field->label }}{{ $field->is_required ? ' *' : '' }}</label>
                    @if ($field->field_type === 'textarea')
                        <textarea name="jawaban[{{ $field->id }}]" rows="3" @required($field->is_required)
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">{{ old('jawaban.' . $field->id) }}</textarea>
                    @elseif ($field->field_type === 'select')
                        <select name="jawaban[{{ $field->id }}]" @required($field->is_required)
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                            <option value="">Pilih</option>
                            @foreach ($field->options ?? [] as $opsi)
                                <option value="{{ $opsi }}">{{ $opsi }}</option>
                            @endforeach
                        </select>
                    @else
                        <input
                            type="{{ $field->field_type === 'number' ? 'number' : ($field->field_type === 'date' ? 'date' : 'text') }}"
                            name="jawaban[{{ $field->id }}]"
                            @required($field->is_required)
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @endif
                    @error('jawaban.' . $field->id) <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
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
