@php
    $assignment = $assignment ?? null;
    $val = function (string $field, $default = '') use ($assignment) {
        $current = $assignment?->$field;
        if ($current instanceof \BackedEnum) {
            $current = $current->value;
        }

        return old($field, $current ?? $default);
    };
    $bentukPendidikanAwal = $assignment
        ? ($assignment->bentuk_pendidikan instanceof \BackedEnum ? $assignment->bentuk_pendidikan->value : $assignment->bentuk_pendidikan)
        : (($isPlatform ?? false) ? $val('bentuk_pendidikan', $bentukPendidikanList[0]->value ?? null) : ($activeLembaga->bentuk_pendidikan ?? null));
@endphp

<div
    x-data="{
        bentukPendidikan: @js($bentukPendidikanAwal),
        tingkatOptions: @js($tingkatOptionsByBentuk),
        modeTingkat: @js($val('tingkat') ? 'spesifik' : 'semua'),
        tingkat: @js($val('tingkat')),
        get pillOptions() { return this.tingkatOptions[this.bentukPendidikan] ?? []; },
    }"
    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
>
    <div class="border-b border-gray-100 bg-white px-6 py-4">
        <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
            <x-icon name="group" class="h-4 w-4 text-brand-500" />
            Aturan Kurikulum
        </p>
        <p class="mt-0.5 text-xs text-gray-500">Kurikulum yang berlaku untuk jenjang &amp; tingkat pada tahun ajaran tertentu. Kelas baru akan otomatis mengikuti aturan ini saat dibuat.</p>
    </div>

    <div class="p-6">
        @if ($assignment)
            <div class="mb-5 rounded-xl border border-gray-100 bg-gray-50 p-4">
                <p class="mb-2 text-xs font-semibold text-gray-500">Identitas Aturan (terkunci, tidak bisa diubah)</p>
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-purple-200 bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ $assignment->lembaga?->nama ?? 'Global (Platform Default)' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                        <x-icon name="calendar_month" class="h-3.5 w-3.5" />
                        {{ $assignment->tahunAjaran?->nama ?? '-' }}
                    </span>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
            @if (! $assignment)
                @if ($isPlatform ?? false)
                    <div class="sm:col-span-6">
                        <x-input-label value="Berlaku Untuk" />
                        <x-select name="lembaga_id" class="mt-1.5" :error="$errors->has('lembaga_id')">
                            <option value="" @selected($val('lembaga_id') === '')>— Platform (semua lembaga) —</option>
                            @foreach ($lembagaList as $lembaga)
                                <option value="{{ $lembaga->id }}" @selected($val('lembaga_id') == $lembaga->id)>{{ $lembaga->nama }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('lembaga_id')" class="mt-1.5" />
                    </div>
                @else
                    <div class="sm:col-span-6">
                        <x-input-label value="Berlaku Untuk" />
                        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">Assignment ini akan dibuat untuk lembaga aktif Anda saat ini: <strong class="font-semibold text-gray-900">{{ $activeLembaga->nama }}</strong>.</p>
                    </div>
                @endif

                <div class="sm:col-span-6">
                    <x-input-label value="Tahun Ajaran" />
                    <x-select name="tahun_ajaran_id" class="mt-1.5" :error="$errors->has('tahun_ajaran_id')">
                        @foreach ($tahunAjaranList as $ta)
                            <option value="{{ $ta->id }}" @selected($val('tahun_ajaran_id') == $ta->id)>{{ $ta->nama }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('tahun_ajaran_id')" class="mt-1.5" />
                </div>
            @endif

            @if ($isPlatform ?? false)
                <div class="sm:col-span-6">
                    <x-input-label value="Bentuk Pendidikan" />
                    <x-select name="bentuk_pendidikan" x-model="bentukPendidikan" @change="modeTingkat = 'semua'; tingkat = ''" class="mt-1.5" :error="$errors->has('bentuk_pendidikan')">
                        @foreach ($bentukPendidikanList as $bp)
                            <option value="{{ $bp->value }}" @selected($val('bentuk_pendidikan') === $bp->value)>{{ $bp->value }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('bentuk_pendidikan')" class="mt-1.5" />
                </div>
            @else
                @php
                    $lembagaBentukPendidikan = $assignment ? $assignment->lembaga?->bentuk_pendidikan : ($activeLembaga->bentuk_pendidikan ?? null);
                @endphp
                <div class="sm:col-span-6">
                    <x-input-label value="Bentuk Pendidikan" />
                    <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $lembagaBentukPendidikan }} <span class="text-gray-400">(mengikuti bentuk pendidikan lembaga, tidak bisa diubah)</span></p>
                    <input type="hidden" name="bentuk_pendidikan" value="{{ $lembagaBentukPendidikan }}">
                </div>
            @endif

            <div class="sm:col-span-12">
                <x-input-label value="Tingkat" />
                <div class="mt-1.5 flex flex-wrap gap-2">
                    <button type="button" @click="modeTingkat = 'semua'; tingkat = ''"
                        :class="modeTingkat === 'semua' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                        class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition">
                        Semua Tingkat (Default Jenjang)
                    </button>
                    <button type="button" @click="modeTingkat = 'spesifik'"
                        :class="modeTingkat === 'spesifik' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                        class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition">
                        Tingkat Tertentu
                    </button>
                </div>
                <div x-show="modeTingkat === 'spesifik'" x-cloak class="mt-2.5 flex flex-wrap gap-2">
                    <template x-for="opsi in pillOptions" :key="opsi">
                        <button type="button" @click="tingkat = opsi"
                            :class="tingkat === opsi ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            class="rounded-lg border px-3.5 py-1.5 text-sm font-semibold transition"
                            x-text="opsi"
                        ></button>
                    </template>
                </div>
                <input type="hidden" name="tingkat" :value="tingkat">
                <x-input-error :messages="$errors->get('tingkat')" class="mt-1.5" />
            </div>

            <div class="sm:col-span-12">
                <x-input-label value="Kurikulum" />
                <x-select name="kurikulum" class="mt-1.5" :error="$errors->has('kurikulum')">
                    @foreach ($kurikulumList as $k)
                        <option value="{{ $k->value }}" @selected($val('kurikulum') === $k->value)>{{ $k->label() }}</option>
                    @endforeach
                </x-select>
                <x-input-error :messages="$errors->get('kurikulum')" class="mt-1.5" />
            </div>

            @if ($assignment)
                <div class="sm:col-span-12 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-xs text-blue-800">
                    <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" />
                    <p>Perubahan ini hanya berlaku otomatis untuk <strong>kelas baru</strong> yang dibuat setelah ini. Kelas yang sudah ada TIDAK berubah otomatis — gunakan menu <strong>Sinkronisasi Kurikulum Kelas</strong> untuk menyelaraskannya secara sadar.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
        <a href="{{ route('admin.kurikulum-assignment.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-200/50 hover:text-gray-900">Batal</a>
        <x-primary-button type="submit">{{ $submitText ?? 'Simpan' }}</x-primary-button>
    </div>
</div>
