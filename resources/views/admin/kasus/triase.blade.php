<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Triase: {{ $kasus->siswa->nama_lengkap }}</h1>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-2">
            <p class="text-xs font-semibold text-gray-500">Kategori</p>
            <p class="text-sm text-gray-900">{{ $kasus->kategori_masalah }}</p>
            <p class="mt-3 text-xs font-semibold text-gray-500">Deskripsi</p>
            <p class="text-sm text-gray-900">{{ $kasus->deskripsi }}</p>
        </div>

        <form method="POST" action="{{ route('admin.kasus.assign-konselor', $kasus) }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            @csrf

            <div>
                <x-input-label value="Tingkat Urgensi *" />
                <select name="tingkat_urgensi" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="rendah">Rendah</option>
                    <option value="sedang">Sedang</option>
                    <option value="tinggi">Tinggi</option>
                </select>
            </div>

            <div>
                <x-input-label value="Pilih Konselor *" />
                @if ($kandidat->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">Menunggu alokasi tenaga ahli — tidak ada Guru BK atau karyawan pool tersedia saat ini.</p>
                @else
                    <div class="mt-2 space-y-2">
                        @foreach ($kandidat as $index => $item)
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                <input type="radio" name="konselor_pilihan" value="{{ $index }}" required
                                    onclick="document.getElementById('konselor_tipe').value='{{ $item->tipe }}'; document.getElementById('konselor_id').value='{{ $item->model->id }}';">
                                <span>{{ $item->model->nama }} <span class="text-xs text-gray-400">({{ $item->tipe === 'guru' ? 'Guru BK' : 'Karyawan Pool' }})</span></span>
                            </label>
                        @endforeach
                    </div>
                    <input type="hidden" name="konselor_tipe" id="konselor_tipe" value="">
                    <input type="hidden" name="konselor_id" id="konselor_id" value="">
                @endif
                <x-input-error :messages="$errors->get('konselor_id')" class="mt-1.5" />
            </div>

            <x-primary-button type="submit" @if($kandidat->isEmpty()) disabled @endif>Tugaskan Konselor</x-primary-button>
        </form>
    </div>
</x-app-layout>
