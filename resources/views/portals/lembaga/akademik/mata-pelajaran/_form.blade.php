@php
    $mataPelajaran = $mataPelajaran ?? null;
    $val = fn (string $field, $default = '') => old($field, $mataPelajaran?->$field instanceof \BackedEnum ? $mataPelajaran->$field->value : ($mataPelajaran?->$field ?? $default));
@endphp

<div class="rounded-2xl border border-gray-200 bg-white shadow-card" x-data="mataPelajaranForm()">
    {{-- Card Header --}}
    <div class="border-b border-gray-100 bg-white px-6 py-4 rounded-t-[15px]">
        <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
            <x-icon name="description" class="h-4 w-4 text-brand-500" />
            Identitas &amp; Klasifikasi Mata Pelajaran
        </p>
        <p class="mt-0.5 text-xs text-gray-500">Sesuaikan kode, nama, nomor urut rapor, serta kelompok mata pelajaran berdasar standar Kemdikdasmen/Kemenag.</p>
    </div>

    {{-- Form Body (Konsisten dengan Layout Komponen Penilaian sm:grid-cols-3) --}}
    <div class="p-6 space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <x-input-label value="Kode Mapel (EMIS/Dapodik) *" />
                <x-text-input type="text" name="kode" value="{{ $val('kode') }}" required placeholder="Misal: MTK-01, PAI-01" class="mt-1.5 block w-full uppercase transition duration-150" />
                <x-input-error :messages="$errors->get('kode')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Nama Mata Pelajaran *" />
                <x-text-input type="text" name="nama" value="{{ $val('nama') }}" required placeholder="Contoh: Matematika, Fikih, Nilai Agama..." class="mt-1.5 block w-full transition duration-150" />
                <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="No. Urut Rapor *" />
                <x-text-input type="number" min="1" max="999" name="no_urut" value="{{ $val('no_urut', '1') }}" required class="mt-1.5 block w-full transition duration-150" />
                <x-input-error :messages="$errors->get('no_urut')" class="mt-1.5" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <x-input-label value="Tipe Kurikulum *" />
                <select name="tipe" required x-ref="tipeInput" x-init="initSelect($refs.tipeInput)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    <option value="mapel" @selected($val('tipe', 'mapel') === 'mapel')>Mata Pelajaran (SD - SMK)</option>
                </select>
                <x-input-error :messages="$errors->get('tipe')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Kelompok Mata Pelajaran" />
                <select name="kelompok" x-ref="kelompokInput" x-init="initSelect($refs.kelompokInput)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    <option value="" @selected($val('kelompok') === '')>-- Tanpa Kelompok / Aspek PAUD --</option>
                    @foreach (\App\Enums\KelompokMataPelajaran::cases() as $k)
                        <option value="{{ $k->value }}" @selected($val('kelompok') === $k->value)>{{ $k->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('kelompok')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Status Keaktifan *" />
                <select name="status" required x-ref="statusInput" x-init="initSelect($refs.statusInput)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                    @foreach (\App\Enums\StatusMataPelajaran::cases() as $s)
                        <option value="{{ $s->value }}" @selected($val('status', 'aktif') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-1.5" />
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
            <x-primary-button type="submit">
                {{ $submitText ?? 'Simpan Mata Pelajaran' }}
            </x-primary-button>
            <a href="{{ route('admin.mata-pelajaran.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">
                Batal
            </a>
        </div>
    </div>
</div>
