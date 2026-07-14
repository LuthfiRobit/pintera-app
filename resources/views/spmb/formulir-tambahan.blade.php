<x-spmb-public-layout :lembaga="$lembaga" title="Formulir Tambahan" :langkah="3">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Formulir Tambahan</h2>

        <form method="POST" action="{{ route('spmb.formulir-tambahan.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-5 space-y-4">
            @csrf

            @forelse ($fieldList as $field)
                <div>
                    <x-input-label :value="$field->label . ($field->is_required ? ' *' : '')" />
                    @if ($field->field_type === 'textarea')
                        <textarea name="jawaban[{{ $field->id }}]" rows="3" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" @required($field->is_required)>{{ old('jawaban.' . $field->id) }}</textarea>
                    @elseif ($field->field_type === 'select')
                        <select name="jawaban[{{ $field->id }}]" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" @required($field->is_required)>
                            <option value="">Pilih</option>
                            @foreach ($field->options ?? [] as $opsi)
                                <option value="{{ $opsi }}">{{ $opsi }}</option>
                            @endforeach
                        </select>
                    @else
                        <x-text-input
                            :type="$field->field_type === 'number' ? 'number' : ($field->field_type === 'date' ? 'date' : 'text')"
                            name="jawaban[{{ $field->id }}]"
                            class="mt-1.5"
                            @required($field->is_required)
                        />
                    @endif
                    <x-input-error :messages="$errors->get('jawaban.' . $field->id)" class="mt-1.5" />
                </div>
            @empty
                <p class="text-sm text-slate">Tidak ada formulir tambahan untuk jalur ini.</p>
            @endforelse

            <x-primary-button>Lanjut ke Upload Dokumen</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
