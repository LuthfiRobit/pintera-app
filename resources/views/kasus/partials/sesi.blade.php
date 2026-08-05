<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
    <p class="font-display text-sm font-bold text-gray-900">Sesi Pendampingan</p>

    @if ($kasus->sesi->isNotEmpty())
        <div class="space-y-2">
            @foreach ($kasus->sesi as $sesi)
                <div class="rounded-lg border border-gray-100 px-3 py-2 space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $sesi->dijadwalkan_pada->format('d M Y H:i') }}</p>
                            <p class="text-xs text-gray-500">{{ $sesi->lokasi_mode }} &middot; {{ ucfirst(str_replace('_', ' ', $sesi->peserta)) }}</p>
                        </div>
                        <x-badge tone="{{ $sesi->status->value === 'selesai' ? 'green' : ($sesi->status->value === 'terjadwal' ? 'blue' : 'slate') }}">
                            {{ $sesi->status->label() }}
                        </x-badge>
                    </div>

                    @if ($isKonselor && $sesi->status->value === 'terjadwal')
                        <div x-data="{ aksi: null }" class="border-t border-gray-100 pt-2">
                            <div class="flex items-center gap-3 text-xs">
                                <button type="button" @click="aksi = (aksi === 'selesai' ? null : 'selesai')" class="font-semibold text-success-600 hover:text-success-700">Tandai Selesai</button>
                                <button type="button" @click="aksi = (aksi === 'batal' ? null : 'batal')" class="font-semibold text-error-600 hover:text-error-700">Batalkan</button>
                                <form method="POST" action="{{ route('kasus.sesi.update-status', [$kasus, $sesi]) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="tidak_hadir">
                                    <button type="submit" class="font-semibold text-gray-500 hover:text-gray-700">Tandai Tidak Hadir</button>
                                </form>
                            </div>
                            <form x-show="aksi === 'selesai'" method="POST" action="{{ route('kasus.sesi.update-status', [$kasus, $sesi]) }}" class="mt-2 space-y-2">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="selesai">
                                <textarea name="catatan_internal" rows="2" placeholder="Catatan internal (rahasia, hanya konselor & admin)" class="block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                <x-primary-button type="submit" class="text-xs">Simpan</x-primary-button>
                            </form>
                            <form x-show="aksi === 'batal'" method="POST" action="{{ route('kasus.sesi.update-status', [$kasus, $sesi]) }}" class="mt-2 space-y-2">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="batal">
                                <textarea name="alasan_batal" rows="2" placeholder="Alasan pembatalan" class="block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                <x-primary-button type="submit" class="text-xs">Simpan</x-primary-button>
                            </form>
                        </div>
                    @endif

                    @if (($isKonselor || $isTriaseAdmin) && $sesi->catatan_internal)
                        <p class="border-t border-gray-100 pt-2 text-xs text-gray-500"><span class="font-semibold">Catatan internal:</span> {{ $sesi->catatan_internal }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <p class="text-xs text-gray-500 italic">Belum ada jadwal sesi pendampingan terdaftar.</p>
    @endif

    @if ($isKonselor && in_array($kasus->status->value, ['ditugaskan', 'berjalan', 'eskalasi'], true))
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
