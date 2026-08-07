<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-6">
    <div class="border-b border-gray-100 pb-4">
        <h3 class="font-display text-base font-bold text-gray-900">Riwayat Evaluasi & Keputusan Kasus</h3>
        <p class="text-xs text-gray-500 mt-0.5">Catatan perkembangan akhir bimbingan dan pen penentuan resolusi atau eskalasi kasus.</p>
    </div>

    {{-- Evaluation History List --}}
    @if ($kasus->evaluasi->isNotEmpty())
        <div class="space-y-4">
            @foreach ($kasus->evaluasi as $evaluasi)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-2xs space-y-2.5 transition hover:border-gray-300">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2.5">
                        <span class="text-xs font-bold text-gray-500 flex items-center gap-1.5">
                            <x-icon name="fact_check" class="h-4 w-4 text-gray-400" />
                            {{ $evaluasi->tanggal->format('d M Y H:i') }} WIB
                        </span>
                        <x-badge :tone="$evaluasi->keputusan === 'eskalasi' ? 'red' : ($evaluasi->keputusan === 'selesai' ? 'green' : 'blue')" class="text-xs font-bold px-3 py-0.5">
                            {{ ucfirst($evaluasi->keputusan) }}
                        </x-badge>
                    </div>
                    <p class="text-sm leading-relaxed font-medium text-gray-800">{{ $evaluasi->catatan }}</p>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/50 p-8 text-center text-gray-400">
            <x-icon name="flaky" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
            <p class="text-sm font-bold text-gray-600">Belum Ada Catatan Evaluasi</p>
            <p class="mt-0.5 text-xs text-gray-400 max-w-md mx-auto">Konselor akan melakukan evaluasi hasil pendampingan setelah serangkaian sesi dan tugas diselesaikan.</p>
        </div>
    @endif

    {{-- Evaluation Form (Konselor) --}}
    @if ($isKonselor && $kasus->status->value === 'berjalan')
        <form method="POST" action="{{ route('kasus.evaluasi.store', $kasus) }}" class="rounded-xl border border-blue-200 bg-blue-50/20 p-5 space-y-4 shadow-2xs mt-6">
            @csrf
            <div class="border-b border-blue-100 pb-3">
                <h4 class="font-display text-sm font-bold text-blue-900 flex items-center gap-1.5">
                    <x-icon name="edit_note" class="h-5 w-5 text-blue-600" />
                    Buat Evaluasi Berkala (Konselor)
                </h4>
                <p class="text-xs text-blue-700 mt-0.5">Tuliskan analisis kemajuan siswa dan tentukan apakah konseling akan dilanjutkan, selesai, atau dileskalasi ke manajemen.</p>
            </div>
            <div>
                <x-input-label value="Catatan Evaluasi / Rekomendasi *" class="text-xs font-bold text-gray-700" />
                <x-textarea name="catatan" rows="3" required placeholder="Jelaskan kemajuan perilaku siswa, hasil konseling, atau alasan eskalasi..." class="mt-1.5 block w-full"></x-textarea>
            </div>
            <div>
                <x-input-label value="Keputusan Selanjutnya *" class="text-xs font-bold text-gray-700" />
                <x-select name="keputusan" class="mt-1.5 block w-full max-w-sm">
                    <option value="lanjut">🔵 Lanjut &mdash; Sesi bimbingan tetap berlangsung</option>
                    <option value="eskalasi">🔴 Eskalasi ke Admin &mdash; Kasus rumit / butuh tindakan manajemen</option>
                    <option value="selesai">🟢 Selesai &mdash; Pembinaan berhasil ditutup</option>
                </x-select>
            </div>
            <div class="flex justify-end pt-2">
                <x-primary-button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-bold shadow-sm">
                    <x-icon name="save" class="mr-1.5 h-4 w-4" />
                    Simpan Evaluasi
                </x-primary-button>
            </div>
        </form>
    @endif

    {{-- Evaluation Form (Triase Admin / Manajemen pada status Eskalasi) --}}
    @if ($isTriaseAdmin && $kasus->status->value === 'eskalasi')
        <form method="POST" action="{{ route('kasus.evaluasi.store', $kasus) }}" class="rounded-xl border border-red-200 bg-red-50/20 p-5 space-y-4 shadow-2xs mt-6">
            @csrf
            <div class="border-b border-red-100 pb-3">
                <h4 class="font-display text-sm font-bold text-red-900 flex items-center gap-1.5">
                    <x-icon name="gavel" class="h-5 w-5 text-red-600" />
                    Tindakan Resolusi Manajemen (Eskalasi)
                </h4>
                <p class="text-xs text-red-700 mt-0.5">Kasus ini dikirim oleh konselor untuk ditindaklanjuti pada level administrasi/manajemen institusi.</p>
            </div>
            <div>
                <x-input-label value="Catatan Evaluasi / Keputusan Pimpinan *" class="text-xs font-bold text-gray-700" />
                <x-textarea name="catatan" rows="3" required placeholder="Tuliskan arahan kepolisisan sekolah atau penyelesaian akhir atas kasus eskalasi ini..." class="mt-1.5 block w-full"></x-textarea>
            </div>
            <div>
                <x-input-label value="Keputusan Resolusi *" class="text-xs font-bold text-gray-700" />
                <x-select name="keputusan" class="mt-1.5 block w-full max-w-md">
                    <option value="lanjut">🔵 Kembalikan ke Konselor &mdash; Melanjutkan pembinaan normal</option>
                    <option value="selesai">🟢 Selesai &mdash; Kasus diselesaikan secara manajerial</option>
                </x-select>
            </div>
            <div class="flex justify-end pt-2">
                <x-primary-button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-red-600 hover:bg-red-700 shadow-sm">
                    <x-icon name="done_all" class="mr-1.5 h-4 w-4" />
                    Simpan Evaluasi
                </x-primary-button>
            </div>
        </form>
    @endif
</div>
