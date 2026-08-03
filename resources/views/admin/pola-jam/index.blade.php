<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6" x-data="polaJamController()">
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
                    <x-primary-button type="button" @click="openCreatePola(@js(route('admin.pola-jam.store')))" class="shrink-0 justify-center">
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
                            @can('pola-jam.edit')
                                <button type="button" @click="openEditPola({{ $pola->id }}, @js($pola->nama), @js(route('admin.pola-jam.update', $pola)))" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition">
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
                                    @click="openAssignModal({{ $pola->id }}, @js($pola->nama), {{ $pola->lembaga_id ?? 'null' }}, @js($pola->kelas->pluck('id')->values()->all()), @js(route('admin.pola-jam.assign-kelas', $pola)))" 
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

                    {{-- 4. Daftar Jam Pelajaran per Hari --}}
                    <div class="divide-y divide-gray-100 bg-white">
                        @if ($pola->jamPelajaran->isEmpty())
                            <div class="px-6 py-10 text-center text-xs text-gray-400 italic">
                                Belum ada slot jam pelajaran yang didaftarkan pada pola ini.
                            </div>
                        @else
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
                                                                    @click="openEditSlot({{ $slot->id }}, @js($slot->hari->value), {{ $slot->urutan }}, @js($slot->jam_mulai), @js($slot->jam_selesai), @js($slot->label), {{ $slot->is_pelajaran ? 1 : 0 }}, @js(route('admin.jam-pelajaran.update', $slot)))" 
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

    @push('scripts')
    <script>
        function polaJamController() {
            return {
                showModalPola: false,
                modalPolaMode: 'create',
                formPola: { id: null, nama: '', actionUrl: '' },

                showModalEditSlot: false,
                formSlot: { id: null, hari: 'senin', urutan: 1, jam_mulai: '', jam_selesai: '', label: '', is_pelajaran: 1, updateUrl: '' },

                showModalAssign: false,
                formAssign: { polaId: null, polaNama: '', lembagaId: null, selectedKelasIds: [], actionUrl: '' },

                openCreatePola(url) {
                    this.modalPolaMode = 'create';
                    this.formPola = { id: null, nama: '', actionUrl: url };
                    this.showModalPola = true;
                },

                openEditPola(id, nama, url) {
                    this.modalPolaMode = 'edit';
                    this.formPola = { id: id, nama: nama, actionUrl: url };
                    this.showModalPola = true;
                },

                openEditSlot(id, hari, urutan, jamMulai, jamSelesai, label, isPelajaran, url) {
                    this.formSlot = {
                        id: id,
                        hari: hari,
                        urutan: urutan,
                        jam_mulai: jamMulai,
                        jam_selesai: jamSelesai,
                        label: label,
                        is_pelajaran: isPelajaran,
                        updateUrl: url
                    };
                    this.showModalEditSlot = true;
                },

                openAssignModal(polaId, polaNama, lembagaId, currentKelasIds, url) {
                    this.formAssign = {
                        polaId: polaId,
                        polaNama: polaNama,
                        lembagaId: lembagaId,
                        selectedKelasIds: Array.from(currentKelasIds).map(Number),
                        actionUrl: url
                    };
                    this.showModalAssign = true;
                }
            }
        }
    </script>
    @endpush
</x-app-layout>