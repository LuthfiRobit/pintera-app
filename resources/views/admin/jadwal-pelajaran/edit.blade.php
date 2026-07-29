<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Jadwal — {{ $kelas->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $jadwalPelajaran->semester_id]) }}" class="font-semibold text-gray-700 hover:text-brand-600">Jadwal Pelajaran</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-xl bg-brand-50 px-4 py-3 text-sm">
            <p><span class="text-brand-400">Tahun Ajaran</span> <span class="ml-1.5 font-semibold text-brand-700">{{ $kelas->tahunAjaran->nama }}</span></p>
            <p><span class="text-brand-400">Semester</span> <span class="ml-1.5 font-semibold text-brand-700">{{ $semester->nama ?? '—' }}</span></p>
            <p><span class="text-brand-400">Kelas</span> <span class="ml-1.5 font-semibold text-brand-700">{{ $kelas->nama }}</span></p>
        </div>

        @unless ($jamPelajaranPerHari->isEmpty() || $slotMasihValid)
            <div class="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700">
                <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
                <p>Slot jam pelajaran yang tercatat di jadwal ini sudah tidak tersedia di Pola Jam kelas saat ini (kemungkinan Pola Jam kelas sudah diganti). Silakan pilih slot yang baru sebelum menyimpan.</p>
            </div>
        @endunless

        @if ($jamPelajaranPerHari->isEmpty())
            <div class="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-5 text-sm text-warning-700">
                <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
                <div>
                    <p class="font-semibold">Kelas ini belum punya Pola Jam</p>
                    <p class="mt-1">Atur Pola Jam terlebih dahulu sebelum mengedit jadwal pelajaran untuk kelas ini.</p>
                    <a href="{{ route('admin.pola-jam.index') }}" class="mt-2 inline-block font-semibold text-warning-800 underline">Buka halaman Pola Jam &rarr;</a>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('admin.jadwal-pelajaran.update', $jadwalPelajaran) }}" x-data="jadwalPelajaranCreateForm()">
                @csrf
                @method('PUT')

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-700 border-b border-gray-100 pb-3">
                        <x-icon name="schedule" class="h-[15px] w-[15px] text-gray-400" />
                        Penempatan Slot &amp; Pengajar
                    </p>

                    <div>
                        <x-input-label value="Jam Pelajaran" />
                        <select
                            name="jam_pelajaran_id"
                            x-ref="jamPelajaranSelect"
                            x-init="initJamPelajaranSelect($refs.jamPelajaranSelect, 'Pilih slot jam pelajaran...')"
                            class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            required
                        >
                            @unless ($slotMasihValid)
                                <option value="" selected disabled>— Slot lama sudah tidak berlaku, pilih ulang —</option>
                            @endunless
                            @foreach ($jamPelajaranPerHari as $grup)
                                <optgroup label="{{ $grup['hari']->label() }}">
                                    @foreach ($grup['items'] as $jam)
                                        <option value="{{ $jam->id }}" @selected($jam->id == old('jam_pelajaran_id', $jadwalPelajaran->jam_pelajaran_id))>{{ substr($jam->jam_mulai, 0, 5) }}–{{ substr($jam->jam_selesai, 0, 5) }} ({{ $jam->label }})</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('jam_pelajaran_id')" class="mt-1.5" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Mata Pelajaran" />
                            <select
                                name="mata_pelajaran_id"
                                x-ref="mataPelajaranSelect"
                                x-init="initMataPelajaranSelect($refs.mataPelajaranSelect)"
                                class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option value="">— Tidak ada —</option>
                                @foreach ($mataPelajaranList as $mapel)
                                    <option value="{{ $mapel->id }}" @selected($mapel->id == old('mata_pelajaran_id', $jadwalPelajaran->mata_pelajaran_id))>{{ $mapel->nama }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-xs text-gray-400">Opsional untuk kelas PAUD.</p>
                            <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1.5" />
                        </div>

                        <div>
                            <x-input-label value="Guru Pengampu" />
                            <select
                                name="guru_id"
                                x-ref="guruSelect"
                                x-init="initGuruSelect($refs.guruSelect)"
                                class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option value="" disabled>— Pilih Guru —</option>
                                @foreach ($guruList as $guru)
                                    <option value="{{ $guru->id }}" @selected($guru->id == old('guru_id', $jadwalPelajaran->guru_id))>{{ $guru->nama }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('guru_id')" class="mt-1.5" />
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                    <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $jadwalPelajaran->semester_id]) }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Batal</a>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
