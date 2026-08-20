<div class="space-y-6">
    {{-- Rincian Kasus Card --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-5">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
            <div class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                    <x-icon name="info" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Informasi Umum</p>
                    <p class="font-display text-sm font-bold text-gray-900">Deskripsi & Catatan Permasalahan</p>
                </div>
            </div>
            <x-badge :tone="$kasus->status->badgeTone()">{{ $kasus->status->label() }}</x-badge>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 bg-gray-50/70 p-4 rounded-xl border border-gray-100/80">
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Kategori Masalah</p>
                <p class="mt-1 text-sm font-bold text-gray-900">{{ $kasus->kategori_masalah }}</p>
            </div>
            @if ($kasus->konselorGuru || $kasus->konselorKaryawan)
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Konselor Penanganan</p>
                    <p class="mt-1 text-sm font-bold text-brand-700">{{ $kasus->konselorGuru?->nama ?? $kasus->konselorKaryawan?->nama }}</p>
                </div>
            @else
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Konselor Penanganan</p>
                    <p class="mt-1 text-sm font-medium italic text-amber-600">Menunggu Triase & Penugasan</p>
                </div>
            @endif
        </div>

        <div>
            <p class="text-xs font-semibold text-gray-500 mb-2">Deskripsi Rinci Permasalahan:</p>
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm leading-relaxed text-gray-800 font-medium shadow-2xs">
                {{ $kasus->deskripsi }}
            </div>
        </div>

        @if ($kasus->lampiran)
            <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <x-icon name="attach_file" class="h-4 w-4 text-gray-400" />
                    <span class="font-medium">Lampiran Bukti / Dokumen Kasus</span>
                </div>
                <a href="{{ asset('storage/' . $kasus->lampiran) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700 hover:underline">
                    Lihat Lampiran
                    <x-icon name="open_in_new" class="h-3.5 w-3.5" />
                </a>
            </div>
        @endif
    </div>

    {{-- Consent Section (Khusus Kontak Utama) --}}
    @if ($isKontakUtama && $kasus->consents->isNotEmpty())
        <div class="rounded-2xl border-2 border-amber-200 bg-amber-50/30 p-6 shadow-card space-y-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                    <x-icon name="verified_user" class="h-6 w-6" />
                </span>
                <div>
                    <p class="font-display text-base font-bold text-gray-900">Persetujuan Tindakan (Informed Consent)</p>
                    <p class="text-xs text-gray-600">Sebagai Kontak Utama, persetujuan Anda diperlukan untuk memulai prosedur penanganan bersama konselor terlampir.</p>
                </div>
            </div>

            <div class="space-y-3 pt-1">
                @foreach ($kasus->consents as $consent)
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-amber-200/80 bg-white p-4 shadow-2xs transition hover:border-amber-300">
                        <div class="space-y-1">
                            <p class="text-sm font-bold text-gray-900">
                                {{ $consent->jenis === 'sesi_pendampingan' ? 'Sesi Pendampingan Konselling/Psikologi' : 'Pengumpulan Media (Foto/Video Bukti)' }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $consent->jenis === 'sesi_pendampingan' ? 'Mengecualikan jadwal rutin dan mengaktifkan diskusi konselor dengan anak.' : 'Mengizinkan unggah dokumen gambar atau rekaman sebagai bukti tugas bimbingan.' }}
                            </p>
                            <div class="pt-1">
                                <x-badge :tone="$consent->status === 'disetujui' ? 'green' : 'amber'" class="text-[11px] font-bold px-2 py-0.5">
                                    {{ $consent->status === 'disetujui' ? '✓ Disetujui' : '⏳ Menunggu Persetujuan' }}
                                </x-badge>
                            </div>
                        </div>

                        @if ($consent->status !== 'disetujui')
                            <form method="POST" action="{{ route('kasus.consent.approve', [$kasus, $consent]) }}" class="shrink-0">
                                @csrf
                                @method('PATCH')
                                <x-primary-button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-700 shadow-sm">
                                    <x-icon name="check" class="mr-1.5 h-4 w-4" />
                                    Setujui
                                </x-primary-button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
