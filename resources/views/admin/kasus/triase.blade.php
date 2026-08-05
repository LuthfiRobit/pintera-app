<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Triase: {{ $kasus->siswa->nama_lengkap }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.kasus.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Triase Kasus</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tugaskan Konselor</b>
            </p>
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
                    <div class="mt-2 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        @foreach ($kandidat as $index => $item)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-3 text-sm transition hover:border-brand-500 hover:bg-brand-50/20 shadow-sm">
                                <input type="radio" name="konselor_pilihan" value="{{ $index }}" required
                                    onclick="document.getElementById('konselor_tipe').value='{{ $item->tipe }}'; document.getElementById('konselor_id').value='{{ $item->model->id }}';"
                                    class="text-brand-600 focus:ring-brand-500">
                                <div class="flex flex-col">
                                    <span class="font-semibold text-gray-900">{{ $item->model->nama }}</span>
                                    <span class="text-xs text-gray-400">{{ $item->tipe === 'guru' ? 'Guru BK' : 'Karyawan Pool' }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <input type="hidden" name="konselor_tipe" id="konselor_tipe" value="">
                    <input type="hidden" name="konselor_id" id="konselor_id" value="">
                @endif
                <x-input-error :messages="$errors->get('konselor_id')" class="mt-1.5" />
            </div>

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button type="submit" @if($kandidat->isEmpty()) disabled @endif>Tugaskan Konselor</x-primary-button>
                <a href="{{ route('admin.kasus.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
