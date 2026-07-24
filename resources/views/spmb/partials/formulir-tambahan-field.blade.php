{{-- resources/views/spmb/partials/formulir-tambahan-field.blade.php --}}
@php $jumlahOpsi = count($field->options ?? []); @endphp
<div>
    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">{{ $field->label }}{{ $field->is_required ? ' *' : '' }}</label>

    @if ($field->field_type === 'textarea')
        <textarea name="jawaban[{{ $field->id }}]" rows="3" placeholder="Masukkan {{ Str::lower($field->label) }}" @required($field->is_required)
            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">{{ old('jawaban.' . $field->id) }}</textarea>

    @elseif ($field->field_type === 'select')
        {{--
            Tom Select copies the raw <select>'s class attribute onto the wrapper it
            builds around it, so styling utility classes here would double up with the
            .ts-control theming in app.css once enhanced. Only apply them when the field
            stays a plain native select (<=10 options, matching the JS threshold below).
        --}}
        <select name="jawaban[{{ $field->id }}]" x-ref="select{{ $field->id }}" x-init="initSelectField($refs.select{{ $field->id }}, {{ $jumlahOpsi }})" @required($field->is_required)
            @class([
                'field-select field-select-chevron w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20' => $jumlahOpsi <= 10,
            ])>
            <option value="">Pilih {{ Str::lower($field->label) }}</option>
            @foreach ($field->options ?? [] as $opsi)
                <option value="{{ $opsi }}" @selected(old('jawaban.' . $field->id) === $opsi)>{{ $opsi }}</option>
            @endforeach
        </select>

    @elseif ($field->field_type === 'date')
        <input type="text" name="jawaban[{{ $field->id }}]" value="{{ old('jawaban.' . $field->id) }}" placeholder="Pilih tanggal" autocomplete="off"
            x-ref="tanggal{{ $field->id }}" x-init="initDateField($refs.tanggal{{ $field->id }})" @required($field->is_required)
            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">

    @elseif ($field->field_type === 'file')
        <x-portal-file-dropzone name="jawaban[{{ $field->id }}]" :required="$field->is_required" />

    @else
        <input
            type="{{ $field->field_type === 'number' ? 'number' : 'text' }}"
            name="jawaban[{{ $field->id }}]"
            value="{{ old('jawaban.' . $field->id) }}"
            placeholder="{{ $field->field_type === 'number' ? 'Masukkan angka' : 'Masukkan ' . Str::lower($field->label) }}"
            @required($field->is_required)
            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
    @endif

    @error('jawaban.' . $field->id) <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
</div>
