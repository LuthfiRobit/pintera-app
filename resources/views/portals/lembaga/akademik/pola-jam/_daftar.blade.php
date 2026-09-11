@php
    $totalPola = $polaJamList->count();
    $totalKelasTertaut = $polaJamList->flatMap->kelas->pluck('id')->unique()->count();
@endphp
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                <x-icon name="schedule" class="h-5 w-5" />
            </span>
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Pola Jam</p>
                <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalPola }}</p>
            </div>
        </div>
        <span class="text-[11px] font-medium text-gray-400">Pola Jadwal</span>
    </div>
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                <x-icon name="school" class="h-5 w-5" />
            </span>
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Kelas Tertaut</p>
                <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalKelasTertaut }}</p>
            </div>
        </div>
        <span class="text-[11px] font-medium text-gray-400">Kelas Aktif &amp; Arsip</span>
    </div>
</div>

{{-- Daftar Card Pola Jam --}}
<div class="space-y-6">
    @forelse ($polaJamList as $pola)
        @php $hariAktifPola = \App\Enums\Hari::aktifDari($pola->lembaga->hari_libur_mingguan ?? []); @endphp
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-elevated transition-all">
            
            {{-- 1. Card Header: Nama Pola Jam & Aksi --}}
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 bg-white px-6 py-4">
                <div class="flex items-center gap-3">
                    <h2 class="font-display text-lg font-bold text-gray-900">{{ $pola->nama }}</h2>
                    <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-600 ring-1 ring-inset ring-brand-500/20">{{ $pola->jamPelajaran->count() }} slot</span>
                    @if (($isYayasan ?? false) && ! ($activeLembaga ?? null) && $pola->lembaga)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ $pola->lembaga->nama }}</span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    @can('pola-jam.create')
                        <x-tooltip text="Salin / Duplikasi Pola Jam">
                            <form action="{{ route('admin.pola-jam.duplicate', $pola) }}" method="POST" class="inline" x-data
                                  @submit.prevent="confirmDialog(
                                      'Duplikasi Pola Jam?',
                                      @js('Akan membuat pola jam baru \''.$pola->nama.' (Salinan)\' berisi salinan semua '.$pola->jamPelajaran->count().' slot jam dari pola ini. Tautan kelas TIDAK ikut disalin — kelas perlu ditautkan ulang secara manual ke pola baru lewat \'Kelola Tautan\'.'),
                                      { confirmLabel: 'Ya, Duplikasi' }
                                  ).then(confirmed => { if (confirmed) submitAjaxForm($el) })">
                                @csrf
                                <button type="submit" :disabled="submitting"
                                        class="rounded-lg border border-brand-200 bg-brand-50/50 px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100/70 hover:text-brand-800 transition flex items-center gap-1 shadow-2xs disabled:opacity-50">
                                    <x-icon name="content_copy" class="h-3.5 w-3.5" />
                                    <span>Duplikat</span>
                                </button>
                            </form>
                        </x-tooltip>
                    @endcan
                    @can('pola-jam.edit')
                        <x-tooltip text="Edit Nama Pola Jam">
                            <button type="button" @click="openEditPola({{ $pola->withoutRelations()->toJson() }}, '{{ route('admin.pola-jam.update', $pola) }}')" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition">
                                Edit Nama
                            </button>
                        </x-tooltip>
                    @endcan
                    @can('pola-jam.delete')
                        <x-tooltip text="Hapus Pola Jam">
                            <form method="POST" action="{{ route('admin.pola-jam.destroy', $pola) }}" x-data @submit.prevent="confirmDialog('Hapus Pola Jam?', @js('Apakah Anda yakin ingin menghapus pola jam \"' . $pola->nama . '\"?'), { confirmLabel: 'Ya, Hapus', destructive: true }).then(confirmed => { if (confirmed) submitAjaxForm($el) })">
                                @csrf
                                @method('DELETE')
                                <button type="submit" :disabled="submitting" class="rounded-lg border border-error-200 bg-error-50/30 px-3 py-1.5 text-xs font-semibold text-error-600 hover:bg-error-50 hover:text-error-700 transition disabled:opacity-50">
                                    Hapus
                                </button>
                            </form>
                        </x-tooltip>
                    @endcan
                </div>
            </div>

            {{-- 2. Tautan Kelas (Pill Tags & Smart Assign Button) --}}
            @can('kelas.edit')
                <div class="border-b border-gray-100 bg-gray-50/60 px-6 py-3.5" x-data="{ tampilkanArsip: false }">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 flex items-center gap-1">
                                <x-icon name="school" class="h-3.5 w-3.5 text-gray-400" />
                                <span>Tautan Kelas:</span>
                            </span>
                            @php
                                $kelasAktif = $pola->kelas->filter(fn ($k) => $k->tahunAjaran?->status_aktif);
                                $kelasArsip = $pola->kelas->reject(fn ($k) => $k->tahunAjaran?->status_aktif);
                            @endphp
                            <span class="text-xs text-gray-600 font-medium">
                                {{ $kelasAktif->count() }} kelas aktif
                                @if ($kelasArsip->isNotEmpty())
                                    &bull; {{ $kelasArsip->count() }} arsip
                                @endif
                            </span>
                        </div>
                        <button type="button"
                                @click="openAssignModal({{ $pola->withoutRelations()->toJson() }}, {{ $pola->kelas->pluck('id')->values()->toJson() }}, '{{ route('admin.pola-jam.assign-kelas', $pola) }}')"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 transition active:scale-95 shrink-0">
                            <x-icon name="groups" class="h-4 w-4 text-brand-500" />
                            <span>Kelola Tautan</span>
                        </button>
                    </div>

                    @if ($pola->kelas->isEmpty())
                        <p class="mt-2 text-xs text-gray-400 italic">Belum ada kelas yang ditautkan pada pola ini.</p>
                    @else
                        <div class="mt-2.5 flex flex-wrap gap-2">
                            @foreach ($kelasAktif as $kelasTerikat)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white border-brand-200 text-brand-700 ring-1 ring-brand-500/10 border px-3 py-1 text-xs font-semibold shadow-2xs">
                                    <span>{{ $kelasTerikat->nama }}</span>
                                </span>
                            @endforeach
                            @if ($kelasArsip->isNotEmpty())
                                <button type="button" @click="tampilkanArsip = !tampilkanArsip" class="inline-flex items-center gap-1 rounded-full border border-dashed border-gray-300 px-3 py-1 text-xs font-semibold text-gray-500 hover:bg-gray-100 transition">
                                    <span x-text="tampilkanArsip ? 'Sembunyikan arsip' : '+{{ $kelasArsip->count() }} arsip'"></span>
                                </button>
                            @endif
                        </div>
                        @if ($kelasArsip->isNotEmpty())
                            <div x-show="tampilkanArsip" x-cloak class="mt-2 flex flex-wrap gap-2">
                                @foreach ($kelasArsip as $kelasTerikat)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-200/60 border-gray-300 text-gray-600 border px-3 py-1 text-xs font-semibold shadow-2xs">
                                        <span>{{ $kelasTerikat->nama }}</span>
                                        <span class="text-[10px] opacity-70">(&bull; {{ $kelasTerikat->tahunAjaran->nama ?? 'N/A' }})</span>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            @endcan

            {{-- 3. Form Tambah Slot Jam Pelajaran (Fast-Input Inline) --}}
            @can('jam-pelajaran.create')
                <div class="border-b border-gray-100 bg-white px-6 py-5">
                    <p class="mb-4 text-xs font-bold uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                        <x-icon name="assignment_add" class="h-4 w-4 text-brand-500" />
                        <span>Input Slot Jam Pelajaran Baru</span>
                    </p>

                    <form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" class="space-y-4"
                          @submit.prevent="submitAjaxForm($el, () => { $el.reset(); hariTerpilih = []; jamMulai = ''; jamSelesai = ''; })"
                          x-data="{
                        hariTerpilih: [],
                        jamMulai: '',
                        jamSelesai: '',
                        hariAktifValues: @js(collect($hariAktifPola)->pluck('value')->all()),
                        get durasiMenit() {
                            if (! this.jamMulai || ! this.jamSelesai) return null;
                            const [h1, m1] = this.jamMulai.split(':').map(Number);
                            const [h2, m2] = this.jamSelesai.split(':').map(Number);
                            const menit = (h2 * 60 + m2) - (h1 * 60 + m1);
                            return menit > 0 ? menit : null;
                        },
                        pilihPreset(preset) {
                            if (preset === 'semua') {
                                this.hariTerpilih = [...this.hariAktifValues];
                            } else if (preset === 'senin_kamis') {
                                this.hariTerpilih = this.hariAktifValues.filter(h => ['senin', 'selasa', 'rabu', 'kamis'].includes(h));
                            }
                        }
                    }">
                        @csrf
                        <input type="hidden" name="pola_jam_id" value="{{ $pola->id }}">

                        <div>
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <label class="block text-xs font-semibold text-gray-700">Pilih Hari <span class="text-gray-400 font-normal">(bisa lebih dari satu)</span></label>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" @click="pilihPreset('senin_kamis')" class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-50 transition">Senin–Kamis</button>
                                    <button type="button" @click="pilihPreset('semua')" class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-50 transition">Semua Hari</button>
                                    <button type="button" @click="hariTerpilih = []" class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-gray-400 hover:bg-gray-50 transition">Reset</button>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($hariAktifPola as $hari)
                                    <label class="flex cursor-pointer select-none items-center justify-center rounded-lg border px-3.5 py-1.5 text-xs font-semibold transition duration-150 active:scale-[0.97]"
                                           :class="hariTerpilih.includes('{{ $hari->value }}') ? 'border-brand-500 bg-brand-50 text-brand-700 ring-1 ring-brand-500/20 shadow-2xs' : 'border-gray-200 bg-gray-50/50 text-gray-600 hover:border-brand-300 hover:bg-white'">
                                        <input type="checkbox" name="hari[]" value="{{ $hari->value }}" x-model="hariTerpilih" class="sr-only">
                                        {{ $hari->label() }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Urutan Ke- <span class="text-error-500">*</span></label>
                                <input type="number" name="urutan" placeholder="Ke-" min="1" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Mulai <span class="text-error-500">*</span></label>
                                <input x-model="jamMulai" type="time" name="jam_mulai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 font-mono text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Selesai <span class="text-error-500">*</span></label>
                                <input x-model="jamSelesai" type="time" name="jam_selesai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 font-mono text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                                <p x-show="durasiMenit" class="mt-1 text-[11px] font-semibold text-brand-600">Durasi: <span x-text="durasiMenit"></span> menit</p>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Label Slot <span class="text-error-500">*</span></label>
                                <input type="text" name="label" required placeholder="mis. Jam ke-1 / Istirahat" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-1.5 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500" list="preset-label-{{ $pola->id }}">
                                <datalist id="preset-label-{{ $pola->id }}">
                                    <option value="Istirahat">
                                    <option value="Upacara Bendera">
                                    <option value="Sholat Dzuhur">
                                    <option value="Literasi">
                                    <option value="Sholat Dhuha">
                                </datalist>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Sesi</label>
                                <select name="is_pelajaran" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                                    <option value="1">Jam Belajar</option>
                                    <option value="0">Non-pelajaran</option>
                                </select>
                            </div>
                            <div class="sm:col-span-12 flex justify-end">
                                <x-primary-button type="submit" x-bind:disabled="submitting" class="px-6 py-2 text-xs">
                                    Simpan Slot
                                </x-primary-button>
                            </div>
                        </div>
                    </form>
                </div>
            @endcan

            {{-- 4. Daftar Jam Pelajaran (List & Weekly Matrix) --}}
            <div x-data="{ viewMode: 'list', hariAktif: '{{ collect($hariAktifPola)->first()?->value }}' }" class="divide-y divide-gray-100 bg-white">
                @if ($pola->jamPelajaran->isEmpty())
                    <div class="px-6 py-10 text-center text-xs text-gray-400 italic">
                        Belum ada slot jam pelajaran yang didaftarkan pada pola ini.
                    </div>
                @else
                    {{-- Header View Toggle --}}
                    <div class="flex items-center justify-between px-6 py-3 border-b border-gray-100 bg-gray-50/30">
                        <span class="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                            <x-icon name="schedule" class="h-4 w-4 text-brand-500" />
                            <span>Jadwal Jam Pelajaran</span>
                        </span>
                        <div class="inline-flex rounded-lg border border-gray-200 bg-gray-100 p-0.5">
                            <button type="button" @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-white text-gray-900 shadow-2xs font-bold' : 'text-gray-600 hover:text-gray-900 font-medium'" class="flex items-center gap-1 px-3 py-1 rounded-md text-xs transition">
                                <x-icon name="list" class="h-3.5 w-3.5" />
                                <span>Daftar Harian</span>
                            </button>
                            <button type="button" @click="viewMode = 'matrix'" :class="viewMode === 'matrix' ? 'bg-white text-gray-900 shadow-2xs font-bold' : 'text-gray-600 hover:text-gray-900 font-medium'" class="flex items-center gap-1 px-3 py-1 rounded-md text-xs transition">
                                <x-icon name="data_table" class="h-3.5 w-3.5 text-brand-500" />
                                <span>Matriks Mingguan</span>
                            </button>
                        </div>
                    </div>

                    {{-- View Mode: List View (Daftar Harian) --}}
                    <div x-show="viewMode === 'list'">
                        {{-- Tab Filter Hari --}}
                        <div class="flex border-b border-gray-100 bg-gray-50/40 px-6 gap-2 overflow-x-auto">
                            @foreach ($hariAktifPola as $tabHari)
                                <button type="button"
                                        @click="hariAktif = '{{ $tabHari->value }}'"
                                        :class="hariAktif === '{{ $tabHari->value }}' ? 'border-brand-500 text-brand-600 font-bold bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 font-medium'"
                                        class="py-2.5 px-3 border-b-2 text-xs transition flex items-center gap-1.5 whitespace-nowrap">
                                    <span>{{ $tabHari->label() }}</span>
                                    <span class="rounded-full bg-gray-100 px-1.5 py-0.2 text-[10px] text-gray-600 font-semibold"
                                          :class="hariAktif === '{{ $tabHari->value }}' ? 'bg-brand-50 text-brand-700' : ''">
                                        {{ $pola->jamPelajaran->where('hari', $tabHari)->count() }}
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        {{-- Konten List Per Hari --}}
                        @foreach ($hariAktifPola as $daftarHari)
                            <div x-show="hariAktif === '{{ $daftarHari->value }}'" class="divide-y divide-gray-100">
                                @php
                                    $slotsHari = $pola->jamPelajaran->where('hari', $daftarHari)->sortBy('urutan');
                                @endphp

                                @if ($slotsHari->isEmpty())
                                    <div class="px-6 py-8 text-center text-xs text-gray-400 italic">
                                        Belum ada slot jam pelajaran untuk hari {{ $daftarHari->label() }}.
                                    </div>
                                @else
                                    <ul class="divide-y divide-gray-100">
                                        @foreach ($slotsHari as $slot)
                                            @php
                                                $slot->jam_mulai = \Carbon\Carbon::parse($slot->jam_mulai)->format('H:i');
                                                $slot->jam_selesai = \Carbon\Carbon::parse($slot->jam_selesai)->format('H:i');
                                            @endphp
                                            <li class="flex flex-col md:flex-row md:items-center justify-between gap-4 px-6 py-3 transition hover:bg-gray-50/60">
                                                <div class="flex flex-wrap items-center gap-4">
                                                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-gray-100 font-mono text-xs font-bold text-gray-700">{{ $slot->urutan }}</span>
                                                    <div class="flex items-center gap-1.5 font-mono text-xs">
                                                        <span class="rounded bg-brand-50 px-2 py-1 font-bold text-brand-700 ring-1 ring-inset ring-brand-500/20">{{ \Carbon\Carbon::parse($slot->jam_mulai)->format('H:i') }}</span>
                                                        <span class="text-gray-400">&rarr;</span>
                                                        <span class="rounded bg-gray-100 px-2 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-300/50">{{ \Carbon\Carbon::parse($slot->jam_selesai)->format('H:i') }}</span>
                                                    </div>
                                                    <span class="text-[10px] font-semibold text-gray-400">({{ \Carbon\Carbon::parse($slot->jam_mulai)->diffInMinutes(\Carbon\Carbon::parse($slot->jam_selesai)) }} mnt)</span>
                                                    <span class="text-gray-300 hidden md:inline">&bull;</span>
                                                    <div class="flex items-center gap-2.5">
                                                        <span class="text-sm font-bold text-gray-900">{{ $slot->label }}</span>
                                                        @if ($slot->is_pelajaran)
                                                            <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-[11px] font-semibold text-success-700 ring-1 ring-inset ring-success-600/20">Belajar</span>
                                                        @else
                                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-semibold text-gray-600 ring-1 ring-inset ring-gray-400/20">Non-pelajaran</span>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    @can('jam-pelajaran.edit')
                                                        <x-tooltip text="Edit Slot Jam Pelajaran">
                                                            <button type="button"
                                                                    @click="openEditSlot({{ $slot->toJson() }}, '{{ $slot->hari->value }}', '{{ route('admin.jam-pelajaran.update', $slot) }}')"
                                                                    class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition">
                                                                Edit
                                                            </button>
                                                        </x-tooltip>
                                                    @endcan
                                                    @can('jam-pelajaran.delete')
                                                        <x-tooltip text="Hapus Slot Jam Pelajaran">
                                                            <form method="POST" action="{{ route('admin.jam-pelajaran.destroy', $slot) }}" x-data @submit.prevent="confirmDialog('Hapus Jam Pelajaran?', @js('Apakah Anda yakin ingin menghapus slot \"' . $slot->label . '\"?'), { confirmLabel: 'Ya, Hapus', destructive: true }).then(confirmed => { if (confirmed) submitAjaxForm($el) })">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" :disabled="submitting" class="rounded-lg border border-error-200 bg-error-50/30 px-2.5 py-1 text-xs font-semibold text-error-600 hover:bg-error-50 hover:text-error-700 transition disabled:opacity-50">Hapus</button>
                                                            </form>
                                                        </x-tooltip>
                                                    @endcan
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- View Mode: Weekly Matrix --}}
                    <div x-show="viewMode === 'matrix'" class="overflow-x-auto p-4">
                        @php
                            $maxUrutan = $pola->jamPelajaran->max('urutan') ?? 0;
                        @endphp
                        @if ($maxUrutan === 0)
                            <div class="py-8 text-center text-xs text-gray-400 italic">Belum ada slot yang terisi.</div>
                        @else
                            <table class="w-full border-collapse text-left border border-gray-150 rounded-xl overflow-hidden shadow-2xs">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-700 font-display text-[11px] uppercase tracking-wider">
                                        <th class="py-2.5 px-4 w-32 border-r border-gray-200 text-center uppercase tracking-wider font-bold">Jam Ke- / Waktu</th>
                                        @foreach ($hariAktifPola as $hCol)
                                            <th class="py-2.5 px-3 border-r border-gray-200 last:border-r-0 font-bold">{{ $hCol->label() }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @foreach (range(1, $maxUrutan) as $urutan)
                                        <tr class="hover:bg-gray-50/40 transition">
                                            <td class="py-2 px-3 border-r border-gray-100 text-center bg-gray-50/60">
                                                <div class="font-bold text-gray-900 text-sm">Ke-{{ $urutan }}</div>
                                                <div class="text-[11px] text-gray-400 font-medium mt-0.5">lihat per hari &rarr;</div>
                                            </td>
                                            @foreach ($hariAktifPola as $hariCol)
                                                @php
                                                    $cellSlot = $pola->jamPelajaran->where('hari', $hariCol)->where('urutan', $urutan)->first();
                                                    if ($cellSlot) {
                                                        $cellSlot->jam_mulai = \Carbon\Carbon::parse($cellSlot->jam_mulai)->format('H:i');
                                                        $cellSlot->jam_selesai = \Carbon\Carbon::parse($cellSlot->jam_selesai)->format('H:i');
                                                    }
                                                @endphp
                                                <td class="py-2 px-2 border-r border-gray-100 align-top last:border-r-0 w-[14%]">
                                                    @if ($cellSlot)
                                                        <div @can('jam-pelajaran.edit') @click="openEditSlot({{ $cellSlot->toJson() }}, '{{ $cellSlot->hari->value }}', '{{ route('admin.jam-pelajaran.update', $cellSlot) }}')" @endcan
                                                             class="group rounded-lg p-2.5 border transition relative cursor-pointer {{ $cellSlot->is_pelajaran ? 'bg-brand-50/40 border-brand-200 hover:border-brand-400 hover:bg-brand-50/80 text-brand-950' : 'bg-gray-50 border-gray-200 hover:border-gray-300 hover:bg-gray-100/70 text-gray-700' }}">
                                                            <div class="font-bold text-xs leading-tight mb-1 flex items-center justify-between gap-1">
                                                                <span>{{ $cellSlot->label }}</span>
                                                                @can('jam-pelajaran.edit')
                                                                    <x-icon name="edit" class="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 transition text-brand-600 shrink-0" />
                                                                @endcan
                                                            </div>
                                                            <div class="text-[10px] font-mono text-gray-500 font-medium flex items-center gap-1">
                                                                <span>{{ \Carbon\Carbon::parse($cellSlot->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($cellSlot->jam_selesai)->format('H:i') }}</span>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="h-full w-full min-h-[52px] rounded-lg border border-dashed border-gray-150 flex items-center justify-center text-[10px] text-gray-300 italic select-none">
                                                            Kosong
                                                        </div>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endif
            </div>

        </div>
    @empty
        <div class="rounded-2xl border border-gray-200 bg-white px-5 py-12 text-center text-sm text-gray-500 shadow-sm">
            Belum ada pola jam yang dibuat. Silakan klik tombol Tambah Pola Jam di atas untuk memulai.
        </div>
    @endforelse
</div>
