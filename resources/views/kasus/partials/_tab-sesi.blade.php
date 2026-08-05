<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-4">
        <div>
            <h3 class="font-display text-base font-bold text-gray-900">Jadwal & Riwayat Sesi Pendampingan</h3>
            <p class="text-xs text-gray-500 mt-0.5">Daftar pertemuan konseling, diskusi observasi, dan riwayat kehadiran.</p>
        </div>

        @if ($isKonselor && in_array($kasus->status->value, ['ditugaskan', 'berjalan', 'eskalasi'], true))
            <button
                type="button"
                @click="showSesiForm = !showSesiForm"
                :class="showSesiForm ? 'bg-gray-100 text-gray-700 border-gray-300' : 'bg-brand-500 text-white border-transparent shadow-sm hover:bg-brand-600'"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border text-xs font-bold transition whitespace-nowrap"
            >
                <x-icon name="add_circle" class="h-4 w-4" />
                <span x-text="showSesiForm ? 'Tutup Formulir' : '+ Jadwalkan Sesi'">+ Jadwalkan Sesi</span>
            </button>
        @endif
    </div>

    {{-- Toggleable Scheduling Form --}}
    @if ($isKonselor && in_array($kasus->status->value, ['ditugaskan', 'berjalan', 'eskalasi'], true))
        <div x-show="showSesiForm" style="display: none;" x-transition:enter="transition ease-out duration-200" class="rounded-xl border border-brand-200 bg-brand-50/20 p-5 shadow-2xs">
            <h4 class="font-display text-sm font-bold text-gray-900 mb-1">Formulir Penjadwalan Sesi Baru</h4>
            <p class="text-xs text-gray-500 mb-4">Anda dapat menjadwalkan satu atau beberapa pertemuan sekaligus untuk siswa dan orang tua.</p>

            <div x-data="{
                rows: [{ dijadwalkan_pada: '', peserta: 'siswa', lokasi_mode: '' }],
                tambah() { this.rows.push({ dijadwalkan_pada: '', peserta: 'siswa', lokasi_mode: '' }); },
                hapus(i) { if (this.rows.length > 1) this.rows.splice(i, 1); },
            }">
                <form method="POST" action="{{ route('kasus.sesi.store', $kasus) }}" class="space-y-4">
                    @csrf
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-2xs sm:grid-cols-12 sm:items-end transition hover:border-brand-300">
                            <div class="sm:col-span-4">
                                <x-input-label value="Tanggal & Jam *" class="text-xs font-bold text-gray-700" />
                                <input type="datetime-local" :name="`sesi[${i}][dijadwalkan_pada]`" x-model="row.dijadwalkan_pada" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div class="sm:col-span-3">
                                <x-input-label value="Peserta *" class="text-xs font-bold text-gray-700" />
                                <select :name="`sesi[${i}][peserta]`" x-model="row.peserta" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                                    <option value="siswa">Siswa</option>
                                    <option value="orang_tua">Orang Tua</option>
                                    <option value="keduanya">Keduanya</option>
                                </select>
                            </div>
                            <div class="sm:col-span-4">
                                <x-input-label value="Lokasi / Mode *" class="text-xs font-bold text-gray-700" />
                                <input type="text" :name="`sesi[${i}][lokasi_mode]`" x-model="row.lokasi_mode" required placeholder="Ruang BK / Google Meet" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div class="sm:col-span-1 flex items-center justify-end sm:justify-center">
                                <button type="button" @click="hapus(i)" x-show="rows.length > 1" title="Hapus baris ini" class="p-2 text-error-500 hover:text-error-700 hover:bg-error-50 rounded-lg transition">
                                    <x-icon name="delete" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </template>
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <button type="button" @click="tambah()" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700 bg-brand-50/50 hover:bg-brand-50 px-3 py-2 rounded-lg transition">
                            <x-icon name="add" class="h-4 w-4" />
                            Tambah Baris
                        </button>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="showSesiForm = false" class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition">
                                Batal
                            </button>
                            <x-primary-button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold shadow-sm">
                                <x-icon name="save" class="mr-1.5 h-4 w-4" />
                                Simpan Jadwal Sesi
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Session List --}}
    @if ($kasus->sesi->isNotEmpty())
        <div class="relative space-y-4 before:absolute before:left-3.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-gray-200">
            @foreach ($kasus->sesi as $sesi)
                <div class="relative pl-9 transition">
                    {{-- Timeline node --}}
                    <span class="absolute left-1.5 top-3 flex h-4 w-4 items-center justify-center rounded-full border-2 bg-white {{ $sesi->status->value === 'selesai' ? 'border-green-500 text-green-500' : ($sesi->status->value === 'terjadwal' ? 'border-blue-500 text-blue-500' : 'border-gray-400 text-gray-400') }}"></span>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-2xs hover:shadow-card transition space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-3">
                            <div>
                                <p class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                    <x-icon name="event" class="h-4 w-4 text-gray-400" />
                                    <span>{{ $sesi->dijadwalkan_pada->format('d M Y H:i') }} WIB</span>
                                </p>
                                <p class="text-xs text-gray-500 mt-1 flex items-center gap-1.5">
                                    <span class="font-semibold text-gray-700">{{ $sesi->lokasi_mode }}</span>
                                    <span>&mdash;</span>
                                    <span class="capitalize">{{ str_replace('_', ' ', $sesi->peserta) }}</span>
                                </p>
                            </div>
                            <x-badge :tone="$sesi->status->value === 'selesai' ? 'green' : ($sesi->status->value === 'terjadwal' ? 'blue' : 'slate')" class="w-fit text-xs font-bold px-3 py-1">
                                {{ $sesi->status->label() }}
                            </x-badge>
                        </div>

                        @if ($isKonselor && $sesi->status->value === 'terjadwal')
                            <div x-data="{ aksi: null }" class="pt-1">
                                <div class="flex flex-wrap items-center gap-3 text-xs">
                                    <button type="button" @click="aksi = (aksi === 'selesai' ? null : 'selesai')" class="inline-flex items-center gap-1 font-bold text-success-600 hover:text-success-700 bg-success-50 px-2.5 py-1.5 rounded-lg transition">
                                        <x-icon name="check_circle" class="h-3.5 w-3.5" />
                                        Tandai Selesai
                                    </button>
                                    <button type="button" @click="aksi = (aksi === 'batal' ? null : 'batal')" class="inline-flex items-center gap-1 font-bold text-error-600 hover:text-error-700 bg-error-50 px-2.5 py-1.5 rounded-lg transition">
                                        <x-icon name="cancel" class="h-3.5 w-3.5" />
                                        Batalkan
                                    </button>
                                    <form method="POST" action="{{ route('kasus.sesi.update-status', [$kasus, $sesi]) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="tidak_hadir">
                                        <button type="submit" class="inline-flex items-center gap-1 font-bold text-gray-600 hover:text-gray-800 bg-gray-100 px-2.5 py-1.5 rounded-lg transition">
                                            <x-icon name="person_off" class="h-3.5 w-3.5" />
                                            Tandai Tidak Hadir
                                        </button>
                                    </form>
                                </div>
                                <form x-show="aksi === 'selesai'" method="POST" action="{{ route('kasus.sesi.update-status', [$kasus, $sesi]) }}" class="mt-3 space-y-2 rounded-lg bg-gray-50 p-3 border border-gray-200">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="selesai">
                                    <label class="block text-xs font-bold text-gray-700">Catatan Hasil Sesi (Rahasia):</label>
                                    <textarea name="catatan_internal" rows="2" placeholder="Catatan internal (rahasia, hanya konselor & admin)..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="aksi = null" class="px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200 rounded-lg">Batal</button>
                                        <x-primary-button type="submit" class="text-xs px-4 py-1.5 rounded-lg font-bold">Simpan & Selesaikan</x-primary-button>
                                    </div>
                                </form>
                                <form x-show="aksi === 'batal'" method="POST" action="{{ route('kasus.sesi.update-status', [$kasus, $sesi]) }}" class="mt-3 space-y-2 rounded-lg bg-error-50/50 p-3 border border-error-200">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="batal">
                                    <label class="block text-xs font-bold text-error-800">Alasan Pembatalan Sesi:</label>
                                    <textarea name="alasan_batal" rows="2" placeholder="Alasan pembatalan..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-error-500 focus:ring-error-500"></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="aksi = null" class="px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200 rounded-lg">Batal</button>
                                        <x-primary-button type="submit" class="text-xs px-4 py-1.5 rounded-lg bg-error-600 hover:bg-error-700 font-bold">Konfirmasi Batal</x-primary-button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        @if (($isKonselor || $isTriaseAdmin) && $sesi->catatan_internal)
                            <div class="rounded-lg bg-amber-50/60 border border-amber-200 p-3 text-xs text-amber-900">
                                <p class="font-bold flex items-center gap-1.5 mb-0.5">
                                    <x-icon name="lock" class="h-3.5 w-3.5 text-amber-600" />
                                    <span>Catatan internal:</span>
                                </p>
                                <p class="text-gray-800 font-medium pl-5">{{ $sesi->catatan_internal }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/50 p-8 text-center text-gray-400">
            <x-icon name="event_note" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
            <p class="text-sm font-bold text-gray-600">Belum Ada Sesi Terjadwal</p>
            <p class="mt-0.5 text-xs text-gray-400 max-w-md mx-auto">Konselor akan menjadwalkan sesi bimbingan atau konseling mendalam secara online maupun offline.</p>
        </div>
    @endif
</div>
