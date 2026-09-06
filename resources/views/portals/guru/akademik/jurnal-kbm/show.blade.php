<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        @if ($terkunci)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Sesi ini sudah melewati batas waktu edit ({{ $batasEditHari }} hari).</p>
                <p class="mt-1 text-xs">Form di bawah ditampilkan hanya untuk dilihat. Hubungi Wali Kelas kelas ini kalau perlu koreksi.</p>
            </div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Isi Jurnal &amp; Presensi</h1>
                <p class="text-xs text-gray-500 mt-0.5">Sesi Pembelajaran Kelas {{ $sesi->kelas->nama }} &middot; {{ $sesi->mataPelajaran?->nama ?? $mapelTerjadwal ?? '(tanpa mapel)' }}</p>
            </div>
            <p class="text-sm text-gray-500">
                <a href="{{ route('guru.jurnal-kbm.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 transition-colors">Jurnal &amp; Presensi</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Isi Sesi</b>
            </p>
        </div>

        {{-- Form Card --}}
        <form method="POST" action="{{ route('guru.jurnal-kbm.update', $sesi) }}">
            @csrf
            @method('PUT')

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                {{-- Card Header --}}
                <div class="border-b border-gray-100 bg-white px-6 py-4">
                    <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
                        <x-icon name="description" class="h-4 w-4 text-brand-500" />
                        Jurnal Mengajar &amp; Presensi Sesi
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500">Isi topik materi pembelajaran hari ini dan perbarui data kehadiran siswa.</p>
                </div>

                {{-- Form Body --}}
                <div class="p-6 space-y-6">
                    {{-- Section 1: Jurnal --}}
                    <div>
                        <x-input-label value="Materi / Jurnal Pembelajaran" />
                        <textarea
                            name="materi"
                            rows="3"
                            placeholder="Contoh: Pembahasan Aljabar, Latihan Soal Halaman 20, dll."
                            class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-gray-50"
                            @disabled($terkunci)
                        >{{ old('materi', $sesi->materi) }}</textarea>
                        <x-input-error :messages="$errors->get('materi')" class="mt-1.5" />
                    </div>

                    @unless ($terkunci)
                    {{-- Scan Presensi via Kartu Digital --}}
                    <div
                        x-data="{
                            showModal: false,
                            pesan: null,
                            pesanTipe: 'success',
                            async kirimKode(kode) {
                                try {
                                    const response = await fetch('{{ route('guru.jurnal-kbm.resolve-kartu', $sesi) }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                                        },
                                        body: JSON.stringify({ kode }),
                                    });
                                    const data = await response.json();
                                    if (response.ok) {
                                        this.pesan = data.nama_lengkap + ' berhasil dicatat Hadir.';
                                        this.pesanTipe = 'success';
                                        window.dispatchEvent(new CustomEvent('presensi-scanned', { detail: { siswaId: data.siswa_id } }));
                                    } else {
                                        this.pesan = data.message;
                                        this.pesanTipe = 'error';
                                    }
                                } catch (e) {
                                    this.pesan = 'Gagal menghubungi server. Periksa koneksi jaringan Anda.';
                                    this.pesanTipe = 'error';
                                }
                            }
                        }"
                        class="rounded-xl border border-gray-200 bg-gray-50/60 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-700">Scan Kartu Digital Siswa (opsional)</p>
                                <p class="text-xs text-gray-500 mt-0.5">Siswa yang scan otomatis tercatat Hadir. Siswa lain tetap diisi manual di tabel bawah.</p>
                            </div>
                            <button type="button" @click="showModal = true; pesan = null" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
                                Scan Presensi
                            </button>
                        </div>

                        <template x-if="pesan">
                            <div
                                class="mt-3 rounded-lg border p-3 text-xs font-semibold"
                                :class="pesanTipe === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
                                x-text="pesan"
                            ></div>
                        </template>

                        {{-- Modal Kamera --}}
                        <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                            <div
                                x-data="qrCameraScanner({
                                    elementId: 'presensi-qr-reader',
                                    onScanSuccess: (decodedText) => { kirimKode(decodedText); },
                                    onCameraError: (msg) => { pesan = msg; pesanTipe = 'error'; }
                                })"
                                x-effect="showModal ? startCamera() : stopCamera()"
                                class="w-full max-w-sm rounded-2xl bg-white p-4 space-y-3"
                            >
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-bold text-gray-900">Scan Kartu Siswa</p>
                                    <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                                </div>
                                <div class="relative mx-auto w-full overflow-hidden rounded-xl border border-gray-800 bg-gray-950">
                                    <div id="presensi-qr-reader" class="aspect-square w-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endunless

                    {{-- Section 2: Presensi --}}
                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <x-icon name="groups" class="h-4 w-4 text-brand-500" />
                            Presensi Kehadiran Siswa
                        </p>

                        <div class="overflow-x-auto rounded-xl border border-gray-200">
                            <table class="w-full min-w-[750px] text-sm">
                                <thead>
                                    <tr class="bg-gray-50 text-left text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                                        <th class="px-5 py-3">Nama Siswa</th>
                                        <th class="px-5 py-3">Status Kehadiran</th>
                                        <th class="px-5 py-3">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-150 bg-white">
                                    @foreach ($presensiList as $presensi)
                                        <tr
                                            x-data="{ status: '{{ $presensi->status->value }}' }"
                                            @presensi-scanned.window="if ($event.detail.siswaId === {{ $presensi->siswa_id }}) status = 'hadir'"
                                            class="transition hover:bg-gray-50/50"
                                        >
                                            <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $presensi->siswa->nama_lengkap }}</td>
                                            <td class="px-5 py-2.5">
                                                <div class="flex flex-row flex-nowrap items-center gap-1.5">
                                                    @foreach (\App\Domains\Akademik\Enums\StatusPresensi::cases() as $status)
                                                        @php
                                                            $theme = match ($status->value) {
                                                                'hadir' => [
                                                                    'active' => 'border-success-500 bg-success-50 text-success-700 ring-1 ring-success-500/20',
                                                                    'hover' => 'hover:border-success-300 hover:bg-success-50/30'
                                                                ],
                                                                'izin' => [
                                                                    'active' => 'border-blue-500 bg-blue-50 text-blue-700 ring-1 ring-blue-500/20',
                                                                    'hover' => 'hover:border-blue-300 hover:bg-blue-50/30'
                                                                ],
                                                                'sakit' => [
                                                                    'active' => 'border-warning-500 bg-warning-50 text-warning-700 ring-1 ring-warning-500/20',
                                                                    'hover' => 'hover:border-warning-300 hover:bg-warning-50/30'
                                                                ],
                                                                'alpa' => [
                                                                    'active' => 'border-error-500 bg-error-50 text-error-700 ring-1 ring-error-500/20',
                                                                    'hover' => 'hover:border-error-300 hover:bg-error-50/30'
                                                                ],
                                                                'terlambat' => [
                                                                    'active' => 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500/20',
                                                                    'hover' => 'hover:border-indigo-300 hover:bg-indigo-50/30'
                                                                ],
                                                                default => [
                                                                    'active' => 'border-brand-500 bg-brand-50 text-brand-700',
                                                                    'hover' => 'hover:bg-gray-50'
                                                                ]
                                                            };
                                                        @endphp
                                                        <label 
                                                            class="flex cursor-pointer items-center justify-center rounded-lg border px-3 py-1.5 text-xs font-semibold transition duration-150 active:scale-[0.97] whitespace-nowrap"
                                                            :class="status === '{{ $status->value }}' ? '{{ $theme['active'] }}' : 'border-gray-200 text-gray-500 {{ $theme['hover'] }}'"
                                                        >
                                                            <input 
                                                                type="radio" 
                                                                name="presensi[{{ $presensi->siswa_id }}]" 
                                                                value="{{ $status->value }}" 
                                                                x-model="status" 
                                                                :disabled="{{ $terkunci ? 'true' : 'false' }}"
                                                                class="sr-only"
                                                            >
                                                            {{ $status->label() }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="px-5 py-2.5">
                                                <template x-if="status === 'izin' || status === 'sakit'">
                                                    <input
                                                        type="text"
                                                        name="keterangan[{{ $presensi->siswa_id }}]"
                                                        value="{{ old('keterangan.'.$presensi->siswa_id, $presensi->keterangan) }}"
                                                        placeholder="Contoh: Demam, surat dari orang tua, dll."
                                                        class="w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-gray-50"
                                                        @disabled($terkunci)
                                                    >
                                                </template>
                                                <template x-if="status !== 'izin' && status !== 'sakit'">
                                                    <span class="text-xs text-gray-400">&mdash;</span>
                                                </template>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

                {{-- Card Footer Action Bar --}}
                <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <a href="{{ route('guru.jurnal-kbm.index') }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors duration-200 hover:bg-gray-200/50 hover:text-gray-900">
                        Batal
                    </a>
                    <x-primary-button type="submit" class="shadow-sm transition-all duration-200 active:scale-[0.98]" :disabled="$terkunci">
                        Simpan Jurnal &amp; Presensi
                    </x-primary-button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>

