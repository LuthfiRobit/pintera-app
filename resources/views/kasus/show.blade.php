{{-- resources/views/kasus/show.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Kasus: {{ $kasus->siswa->nama_lengkap }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('kasus.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Kasus Pendampingan</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Detail</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-3">
            <div class="flex items-center gap-2">
                <x-badge tone="slate">{{ $kasus->status->label() }}</x-badge>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500">Kategori Masalah</p>
                <p class="mt-1 text-sm text-gray-900">{{ $kasus->kategori_masalah }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500">Deskripsi</p>
                <p class="mt-1 text-sm text-gray-900">{{ $kasus->deskripsi }}</p>
            </div>
            @if ($kasus->konselorGuru || $kasus->konselorKaryawan)
                <div>
                    <p class="text-xs font-semibold text-gray-500">Konselor Penanganan</p>
                    <p class="mt-1 text-sm text-gray-900">{{ $kasus->konselorGuru?->nama ?? $kasus->konselorKaryawan?->nama }}</p>
                </div>
            @endif
        </div>

        @if ($isKontakUtama && $kasus->consents->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-3">
                <p class="font-display text-sm font-bold text-gray-900">Persetujuan (Consent)</p>
                @foreach ($kasus->consents as $consent)
                    <div class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">
                                {{ $consent->jenis === 'sesi_pendampingan' ? 'Sesi Pendampingan' : 'Pengumpulan Media (Foto/Video)' }}
                            </p>
                            <x-badge tone="{{ $consent->status === 'disetujui' ? 'green' : 'amber' }}" class="mt-1 text-xs">
                                {{ $consent->status === 'disetujui' ? 'Disetujui' : 'Menunggu Persetujuan' }}
                            </x-badge>
                        </div>
                        @if ($consent->status !== 'disetujui')
                            <form method="POST" action="{{ route('kasus.consent.approve', [$kasus, $consent]) }}">
                                @csrf
                                @method('PATCH')
                                <x-primary-button type="submit">Setujui</x-primary-button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($isKonselor)
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900">Sesi Pendampingan</p>

                @if ($kasus->sesi->isNotEmpty())
                    <div class="space-y-2">
                        @foreach ($kasus->sesi as $sesi)
                            <div class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $sesi->dijadwalkan_pada->format('d M Y H:i') }}</p>
                                    <p class="text-xs text-gray-500">{{ $sesi->lokasi_mode }} &middot; {{ ucfirst(str_replace('_', ' ', $sesi->peserta)) }}</p>
                                </div>
                                <x-badge tone="{{ $sesi->status->value === 'selesai' ? 'green' : ($sesi->status->value === 'terjadwal' ? 'blue' : 'slate') }}">
                                    {{ $sesi->status->label() }}
                                </x-badge>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($kasus->status->value === 'ditugaskan')
                    <div x-data="{
                        rows: [{ dijadwalkan_pada: '', peserta: 'siswa', lokasi_mode: '' }],
                        tambah() { this.rows.push({ dijadwalkan_pada: '', peserta: 'siswa', lokasi_mode: '' }); },
                        hapus(i) { if (this.rows.length > 1) this.rows.splice(i, 1); },
                    }" class="border-t border-gray-100 pt-4">
                        <form method="POST" action="{{ route('kasus.sesi.store', $kasus) }}" class="space-y-3">
                            @csrf
                            <template x-for="(row, i) in rows" :key="i">
                                <div class="grid grid-cols-1 gap-2 rounded-lg border border-gray-100 p-3 sm:grid-cols-4 sm:items-end">
                                    <div>
                                        <x-input-label value="Tanggal & Jam *" />
                                        <input type="datetime-local" :name="`sesi[${i}][dijadwalkan_pada]`" x-model="row.dijadwalkan_pada" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <div>
                                        <x-input-label value="Peserta *" />
                                        <select :name="`sesi[${i}][peserta]`" x-model="row.peserta" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                            <option value="siswa">Siswa</option>
                                            <option value="orang_tua">Orang Tua</option>
                                            <option value="keduanya">Keduanya</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label value="Lokasi/Mode *" />
                                        <input type="text" :name="`sesi[${i}][lokasi_mode]`" x-model="row.lokasi_mode" required placeholder="Ruang BK / Google Meet" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <button type="button" @click="hapus(i)" x-show="rows.length > 1" class="text-xs font-semibold text-error-600 hover:text-error-700">Hapus Baris</button>
                                </div>
                            </template>
                            <div class="flex items-center gap-3">
                                <button type="button" @click="tambah()" class="text-xs font-semibold text-brand-600 hover:text-brand-700">+ Tambah Baris</button>
                                <x-primary-button type="submit">Jadwalkan Sesi</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
