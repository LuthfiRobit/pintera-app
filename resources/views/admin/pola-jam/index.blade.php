<x-app-layout>
    <div x-data="{
        showModalPola: false,
        modalPolaMode: 'create',
        formPola: { id: null, nama: '', actionUrl: '{{ route('admin.pola-jam.store') }}' },

        showModalEditSlot: false,
        formSlot: { id: null, hari: 'senin', urutan: 1, jam_mulai: '', jam_selesai: '', label: '', is_pelajaran: 1, updateUrl: '' },

        showModalAssign: false,
        formAssign: { polaId: null, polaNama: '', lembagaId: null, selectedKelasIds: [], actionUrl: '' },

        openCreatePola() {
            this.modalPolaMode = 'create';
            this.formPola = { id: null, nama: '', actionUrl: '{{ route('admin.pola-jam.store') }}' };
            this.showModalPola = true;
        },

        openEditPola(pola, url) {
            this.modalPolaMode = 'edit';
            this.formPola = { id: pola.id, nama: pola.nama, actionUrl: url };
            this.showModalPola = true;
        },

        openEditSlot(slot, hariValue, url) {
            this.formSlot = {
                id: slot.id,
                hari: hariValue,
                urutan: slot.urutan,
                jam_mulai: slot.jam_mulai ? String(slot.jam_mulai).substring(0, 5) : '',
                jam_selesai: slot.jam_selesai ? String(slot.jam_selesai).substring(0, 5) : '',
                label: slot.label,
                is_pelajaran: slot.is_pelajaran ? 1 : 0,
                updateUrl: url
            };
            this.showModalEditSlot = true;
        },

        openAssignModal(pola, kelasIds, url) {
            this.formAssign = {
                polaId: pola.id,
                polaNama: pola.nama,
                lembagaId: pola.lembaga_id || null,
                selectedKelasIds: Array.from(kelasIds || []).map(Number),
                actionUrl: url
            };
            this.showModalAssign = true;
        }
    }" class="mx-auto max-w-6xl space-y-6">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Pola Jam &amp; Jam Pelajaran</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola jadwal waktu belajar harian dan tautkan dengan kelas yang relevan.</p>
            </div>
            <div class="flex items-center gap-4">
                @can('pola-jam.create')
                    <x-primary-button type="button" @click="openCreatePola()" class="shrink-0 justify-center">
                        <span class="text-base leading-none mr-1.5">+</span> Tambah Pola Jam
                    </x-primary-button>
                @endcan
                <p class="hidden sm:block text-sm text-gray-500">
                    Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pola Jam</b>
                </p>
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
                            @if($pola->lembaga)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ $pola->lembaga->nama }}</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-3">
                            @can('pola-jam.create')
                                <form action="{{ route('admin.pola-jam.duplicate', $pola) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-lg border border-brand-200 bg-brand-50/50 px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100/70 hover:text-brand-800 transition flex items-center gap-1 shadow-2xs"
                                            title="Salin / Duplikasi Pola Jam">
                                        <x-icon name="content_copy" class="h-3.5 w-3.5" />
                                        <span>Duplikat</span>
                                    </button>
                                </form>
                            @endcan
                            @can('pola-jam.edit')
                                <button type="button" @click="openEditPola({{ $pola->toJson() }}, '{{ route('admin.pola-jam.update', $pola) }}')" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition">
                                    Edit Nama
                                </button>
                            @endcan
                            @can('pola-jam.delete')
                                <form method="POST" action="{{ route('admin.pola-jam.destroy', $pola) }}" x-data @submit.prevent="confirmDialog('Hapus Pola Jam?', @js('Apakah Anda yakin ingin menghapus pola jam \"' . $pola->nama . '\"?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-error-200 bg-error-50/30 px-3 py-1.5 text-xs font-semibold text-error-600 hover:bg-error-50 hover:text-error-700 transition">
                                        Hapus
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    {{-- 2. Tautan Kelas (Pill Tags & Smart Assign Button) --}}
                    @can('kelas.edit')
                        <div class="border-b border-gray-100 bg-gray-50/60 px-6 py-3.5 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2 flex-wrap flex-1">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mr-1 flex items-center gap-1">
                                    <x-icon name="class" class="h-3.5 w-3.5 text-gray-400" />
                                    <span>Tautan Kelas:</span>
                                </span>
                                @if ($pola->kelas->isEmpty())
                                    <span class="text-xs text-gray-400 italic">Belum ada kelas yang ditautkan pada pola ini.</span>
                                @else
                                    @foreach ($pola->kelas as $kelasTerikat)
                                        @php
                                            $labelTa = $kelasTerikat->tahunAjaran ? $kelasTerikat->tahunAjaran->nama : 'N/A';
                                            $isActiveTa = $kelasTerikat->tahunAjaran && $kelasTerikat->tahunAjaran->status_aktif;
                                        @endphp
                                        <span class="inline-flex items-center gap-1.5 rounded-full {{ $isActiveTa ? 'bg-white border-brand-200 text-brand-700 ring-1 ring-brand-500/10' : 'bg-gray-200/60 border-gray-300 text-gray-600' }} border px-3 py-1 text-xs font-semibold shadow-2xs">
                                            <span>{{ $kelasTerikat->nama }}</span>
                                            <span class="text-[10px] opacity-70">(&bull; {{ $labelTa }})</span>
                                        </span>
                                    @endforeach
                                @endif
                            </div>
                            <button type="button" 
                                    @click="openAssignModal({{ $pola->toJson() }}, {{ $pola->kelas->pluck('id')->values()->toJson() }}, '{{ route('admin.pola-jam.assign-kelas', $pola) }}')" 
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 transition active:scale-95 shrink-0">
                                <x-icon name="add_circle" class="h-4 w-4 text-brand-500" />
                                <span>Kelola Tautan</span>
                            </button>
                        </div>
                    @endcan

                    {{-- 3. Form Tambah Slot Jam Pelajaran (Fast-Input Inline) --}}
                    @can('jam-pelajaran.create')
                        <div class="border-b border-gray-100 bg-white px-6 py-5">
                            <p class="mb-4 text-xs font-bold uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                                <x-icon name="playlist_add" class="h-4 w-4 text-brand-500" />
                                <span>Input Slot Jam Pelajaran Baru</span>
                            </p>

                            <form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" class="space-y-4" x-data="{ hariTerpilih: [] }">
                                @csrf
                                <input type="hidden" name="pola_jam_id" value="{{ $pola->id }}">

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-gray-700">Pilih Hari <span class="text-gray-400 font-normal">(Bisa pilih lebih dari satu hari sekaligus)</span></label>
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
                                        <input type="time" name="jam_mulai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 font-mono text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Selesai <span class="text-error-500">*</span></label>
                                        <input type="time" name="jam_selesai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 font-mono text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Label Slot <span class="text-error-500">*</span></label>
                                        <input type="text" name="label" required placeholder="mis. Jam ke-1 / Istirahat" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-1.5 text-xs text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Sesi</label>
                                        <select name="is_pelajaran" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                                            <option value="1">Jam Belajar</option>
                                            <option value="0">Non-pelajaran</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <x-primary-button type="submit" class="w-full justify-center py-2 text-xs">
                                            Simpan
                                        </x-primary-button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @endcan

                    {{-- 4. Daftar Jam Pelajaran (List & Weekly Matrix) --}}
                    <div x-data="{ viewMode: 'list' }" class="divide-y divide-gray-100 bg-white">
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
                                        <x-icon name="grid_view" class="h-3.5 w-3.5 text-brand-500" />
                                        <span>Matriks Mingguan</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Mode 1: Daftar Harian --}}
                            <div x-show="viewMode === 'list'" class="divide-y divide-gray-100">
                                @foreach (\App\Enums\Hari::cases() as $hari)
                                    @php $slotHariIni = $pola->jamPelajaran->where('hari', $hari)->sortBy('urutan'); @endphp
                                    @if ($slotHariIni->isNotEmpty())
                                        <div>
                                            <div class="flex items-center justify-between bg-gray-50/40 px-6 py-2.5 border-y border-gray-100 mt-[-1px]">
                                                <p class="text-xs font-bold text-gray-800 flex items-center gap-2">
                                                    <span class="h-2 w-2 rounded-full bg-brand-500"></span>
                                                    <span>{{ $hari->label() }}</span>
                                                </p>
                                                <span class="text-xs font-semibold text-gray-500">{{ $slotHariIni->count() }} sesi terdaftar</span>
                                            </div>

                                            <ul class="divide-y divide-gray-50">
                                                @foreach ($slotHariIni as $slot)
                                                    <li class="flex flex-col md:flex-row md:items-center justify-between gap-4 px-6 py-3 transition hover:bg-gray-50/60">
                                                        <div class="flex flex-wrap items-center gap-4">
                                                            <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-gray-100 font-mono text-xs font-bold text-gray-700">{{ $slot->urutan }}</span>
                                                            <div class="flex items-center gap-1.5 font-mono text-xs">
                                                                <span class="rounded bg-brand-50 px-2 py-1 font-bold text-brand-700 ring-1 ring-inset ring-brand-500/20">{{ $slot->jam_mulai }}</span>
                                                                <span class="text-gray-400">&rarr;</span>
                                                                <span class="rounded bg-gray-100 px-2 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-300/50">{{ $slot->jam_selesai }}</span>
                                                            </div>
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

                                                        <div class="flex items-center gap-3">
                                                            @can('jam-pelajaran.edit')
                                                                <button type="button" 
                                                                        @click="openEditSlot({{ $slot->toJson() }}, '{{ $slot->hari->value }}', '{{ route('admin.jam-pelajaran.update', $slot) }}')" 
                                                                        class="text-xs font-semibold text-gray-500 hover:text-brand-600 transition">
                                                                    Edit
                                                                </button>
                                                            @endcan
                                                            @can('jam-pelajaran.delete')
                                                                <form method="POST" action="{{ route('admin.jam-pelajaran.destroy', $slot) }}" x-data @submit.prevent="confirmDialog('Hapus Jam Pelajaran?', @js('Apakah Anda yakin ingin menghapus slot \"' . $slot->label . '\"?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="text-xs font-semibold text-error-500 hover:text-error-700 transition">Hapus</button>
                                                                </form>
                                                            @endcan
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            {{-- Mode 2: Matriks Mingguan --}}
                            <div x-show="viewMode === 'matrix'" x-cloak style="display: none;" class="overflow-x-auto p-6 bg-gray-50/20 border-b border-gray-100">
                                @php
                                    $allUrutans = $pola->jamPelajaran->pluck('urutan')->unique()->sort()->values();
                                @endphp
                                <table class="w-full border-collapse rounded-xl overflow-hidden shadow-2xs border border-gray-200 bg-white text-left text-xs">
                                    <thead>
                                        <tr class="bg-gray-100 border-b border-gray-200 text-gray-700 font-bold">
                                            <th class="py-2.5 px-4 w-32 border-r border-gray-200 text-center uppercase tracking-wider">Jam Ke- / Waktu</th>
                                            @foreach ($hariAktifPola as $hariCol)
                                                <th class="py-2.5 px-3 border-r border-gray-200 text-center uppercase tracking-wider last:border-r-0">{{ $hariCol->label() }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($allUrutans as $urutan)
                                            <tr class="hover:bg-gray-50/50 transition">
                                                @php
                                                    $sampleSlot = $pola->jamPelajaran->where('urutan', $urutan)->first();
                                                @endphp
                                                <td class="py-3 px-3 border-r border-gray-100 text-center bg-gray-50/40 font-mono shrink-0">
                                                    <div class="font-bold text-gray-900 text-sm">Ke-{{ $urutan }}</div>
                                                    @if ($sampleSlot)
                                                        <div class="text-[11px] text-gray-500 font-semibold mt-0.5">{{ \Carbon\Carbon::parse($sampleSlot->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($sampleSlot->jam_selesai)->format('H:i') }}</div>
                                                    @endif
                                                </td>
                                                @foreach ($hariAktifPola as $hariCol)
                                                    @php
                                                        $cellSlot = $pola->jamPelajaran->where('hari', $hariCol)->where('urutan', $urutan)->first();
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

        {{-- Include SPA Modal Partials --}}
        @include('admin.pola-jam._modal-pola')
        @include('admin.pola-jam._modal-edit-slot')
        @include('admin.pola-jam._modal-assign-kelas')
    </div>
</x-app-layout>