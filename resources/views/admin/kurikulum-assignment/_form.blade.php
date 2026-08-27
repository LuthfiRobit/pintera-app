@php
    $assignment = $assignment ?? null;
    $val = fn (string $field, $default = '') => old($field, $assignment?->$field ?? $default);
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 bg-white px-6 py-4">
        <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
            <x-icon name="group" class="h-4 w-4 text-brand-500" />
            Assignment Kurikulum
        </p>
        <p class="mt-0.5 text-xs text-gray-500">Kurikulum yang berlaku untuk jenjang &amp; tingkat pada tahun ajaran tertentu. Kelas baru akan otomatis mengikuti assignment ini saat dibuat.</p>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            @if ($isPlatformOrYayasan ?? false)
                <div class="sm:col-span-6">
                    <x-input-label value="Berlaku Untuk" />
                    <select name="lembaga_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="" @selected($val('lembaga_id') === '')>— Platform (semua lembaga) —</option>
                        @foreach ($lembagaList as $lembaga)
                            <option value="{{ $lembaga->id }}" @selected($val('lembaga_id') == $lembaga->id)>{{ $lembaga->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('lembaga_id')" class="mt-1.5" />
                </div>
            @endif

            @if (! $assignment)
                <div class="sm:col-span-6">
                    <x-input-label value="Tahun Ajaran" />
                    <select name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected($val('tahun_ajaran_id') == $ta->id)>{{ $ta->nama }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('tahun_ajaran_id')" class="mt-1.5" />
                </div>
            @else
                <div class="sm:col-span-6">
                    <x-input-label value="Tahun Ajaran" />
                    <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $assignment->tahunAjaran->nama }} (tidak bisa diubah setelah dibuat)</p>
                </div>
            @endif

            <div class="sm:col-span-6">
                <x-input-label value="Bentuk Pendidikan" />
                <select name="bentuk_pendidikan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($bentukPendidikanList as $bp)
                        <option value="{{ $bp->value }}" @selected($val('bentuk_pendidikan') === $bp->value)>{{ $bp->value }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('bentuk_pendidikan')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-6">
                <x-input-label value="Tingkat (kosongkan = berlaku semua tingkat)" />
                <x-text-input type="text" name="tingkat" value="{{ $val('tingkat') }}" placeholder="Contoh: 1, 10, A (kosongkan utk catch-all)" class="mt-1.5 w-full" />
                <x-input-error :messages="$errors->get('tingkat')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-12">
                <x-input-label value="Kurikulum" />
                <select name="kurikulum" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($kurikulumList as $k)
                        <option value="{{ $k->value }}" @selected($val('kurikulum') === $k->value)>{{ $k->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('kurikulum')" class="mt-1.5" />
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
        <a href="{{ route('admin.kurikulum-assignment.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-200/50 hover:text-gray-900">Batal</a>
        <x-primary-button type="submit">{{ $submitText ?? 'Simpan' }}</x-primary-button>
    </div>
</div>
