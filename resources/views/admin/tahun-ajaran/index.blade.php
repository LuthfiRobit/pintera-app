<x-app-layout>
    <div x-data="{
        showModalTahunAjaran: false,
        modalTahunAjaranMode: 'create',
        modalTahunAjaranAction: '{{ route('admin.tahun-ajaran.store') }}',
        formTa: { nama: '', tanggal_mulai: '', tanggal_selesai: '' },
        
        showModalSemester: false,
        selectedTaId: null,
        selectedTaName: '',
        formSem: {
            ganjil_kode_dapodik: '',
            ganjil_tanggal_mulai: '',
            ganjil_tanggal_selesai: '',
            genap_kode_dapodik: '',
            genap_tanggal_mulai: '',
            genap_tanggal_selesai: ''
        },

        openCreateTa() {
            this.modalTahunAjaranMode = 'create';
            this.modalTahunAjaranAction = '{{ route('admin.tahun-ajaran.store') }}';
            this.formTa = { nama: '', tanggal_mulai: '', tanggal_selesai: '' };
            this.showModalTahunAjaran = true;
        },
        openEditTa(ta) {
            this.modalTahunAjaranMode = 'edit';
            this.modalTahunAjaranAction = '/admin/tahun-ajaran/' + ta.id;
            this.formTa = { 
                nama: ta.nama, 
                tanggal_mulai: ta.tanggal_mulai ? ta.tanggal_mulai.split('T')[0] : '', 
                tanggal_selesai: ta.tanggal_selesai ? ta.tanggal_selesai.split('T')[0] : '' 
            };
            this.showModalTahunAjaran = true;
        },
        openConfigSemester(ta, semesters) {
            this.selectedTaId = ta.id;
            this.selectedTaName = ta.nama;
            
            let ganjil = semesters.find(s => s.urutan === 1 || s.nama === 'Ganjil') || {};
            let genap = semesters.find(s => s.urutan === 2 || s.nama === 'Genap') || {};

            this.formSem = {
                ganjil_kode_dapodik: ganjil.kode_dapodik || '',
                ganjil_tanggal_mulai: ganjil.tanggal_mulai ? ganjil.tanggal_mulai.split('T')[0] : '',
                ganjil_tanggal_selesai: ganjil.tanggal_selesai ? ganjil.tanggal_selesai.split('T')[0] : '',
                genap_kode_dapodik: genap.kode_dapodik || '',
                genap_tanggal_mulai: genap.tanggal_mulai ? genap.tanggal_mulai.split('T')[0] : '',
                genap_tanggal_selesai: genap.tanggal_selesai ? genap.tanggal_selesai.split('T')[0] : ''
            };
            this.showModalSemester = true;
        }
    }" class="mx-auto max-w-6xl space-y-6">

        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 shadow-xs" x-data>
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100/80 text-emerald-600">
                        <x-icon name="check_circle" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-emerald-900">Berhasil</p>
                        <p class="text-xs text-emerald-700">{{ session('status') }}</p>
                    </div>
                </div>
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 shadow-xs" x-data x-init="$store.toast.push('error', @js($errors->first()))">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-100/80 text-rose-600">
                        <x-icon name="error" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-rose-900">Terjadi Kesalahan</p>
                        <ul class="mt-1 text-xs text-rose-700 list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- Page Header --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">Data Induk</p>
                <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Tahun Ajaran &amp; Semester</h1>
            </div>
            @can('tahun-ajaran.create')
                <x-primary-button type="button" @click="openCreateTa()" class="flex items-center gap-2">
                    <x-icon name="add" class="h-4 w-4" />
                    Tambah Tahun Ajaran
                </x-primary-button>
            @endcan
        </div>

        {{-- Executive Informative Grid Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($tahunAjaranList as $ta)
                @php
                    $semGanjil = $ta->semester->where('urutan', 1)->first() ?? $ta->semester->where('nama', 'Ganjil')->first();
                    $semGenap = $ta->semester->where('urutan', 2)->first() ?? $ta->semester->where('nama', 'Genap')->first();
                    $isTaActive = (bool) $ta->status_aktif;
                @endphp
                <div class="relative flex flex-col justify-between overflow-hidden rounded-2xl border {{ $isTaActive ? 'border-brand-300 bg-gradient-to-b from-brand-50/30 via-white to-white shadow-md shadow-brand-500/5' : 'border-gray-200 bg-white shadow-xs' }} p-6 transition hover:border-gray-300">
                    <div>
                        {{-- Top Bar Card --}}
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-display text-lg font-bold text-gray-900">{{ $ta->nama }}</h3>
                                    @if ($isTaActive)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200 shadow-2xs">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-600">
                                            Non-aktif
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-gray-500">
                                    <x-icon name="event" class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ \Carbon\Carbon::parse($ta->tanggal_mulai)->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($ta->tanggal_selesai)->translatedFormat('d M Y') }}</span>
                                </p>
                            </div>
                            
                            {{-- Actions for TA --}}
                            @can('tahun-ajaran.create')
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="openEditTa({{ $ta->toJson() }})" class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-gray-100 rounded-lg transition" title="Edit Tahun Ajaran">
                                        <x-icon name="edit_square" class="h-4 w-4" />
                                    </button>
                                    @if (!$isTaActive)
                                        @can('tahun-ajaran.activate')
                                            <form action="{{ route('admin.tahun-ajaran.activate', $ta) }}" method="POST" class="inline" onsubmit="return confirm('Aktifkan Tahun Ajaran ini? Tahun Ajaran lain akan dinonaktifkan.')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition" title="Jadikan Tahun Ajaran Aktif">
                                                    <x-icon name="play_circle" class="h-4 w-4" />
                                                </button>
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            @endcan
                        </div>

                        {{-- Divider --}}
                        <div class="my-4 border-t border-gray-100"></div>

                        {{-- Semester Breakdown --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-gray-700 flex items-center gap-1.5">
                                    <x-icon name="data_table" class="h-3.5 w-3.5 text-brand-500" />
                                    Sesi Semester
                                </span>
                                @can('semester.create')
                                    <button type="button" @click="openConfigSemester({{ $ta->toJson() }}, {{ $ta->semester->toJson() }})" class="text-[11px] font-semibold text-brand-600 hover:text-brand-700 flex items-center gap-1 transition">
                                        <x-icon name="settings" class="h-3 w-3" />
                                        Konfigurasi
                                    </button>
                                @endcan
                            </div>

                            {{-- Item Ganjil --}}
                            <div class="flex items-center justify-between rounded-xl bg-gray-50/80 border border-gray-100 p-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-gray-800">Ganjil</span>
                                        @if ($semGanjil && $semGanjil->kode_dapodik)
                                            <span class="rounded bg-white px-1.5 py-0.5 font-mono text-[10px] font-semibold text-gray-600 border border-gray-200 shadow-2xs">{{ $semGanjil->kode_dapodik }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-gray-500">
                                        @if ($semGanjil && $semGanjil->tanggal_mulai)
                                            {{ \Carbon\Carbon::parse($semGanjil->tanggal_mulai)->format('d/m/y') }} - {{ \Carbon\Carbon::parse($semGanjil->tanggal_selesai)->format('d/m/y') }}
                                        @else
                                            <span class="italic text-gray-400">Belum dikonfigurasi</span>
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    @if ($semGanjil)
                                        @if ($semGanjil->status_aktif)
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">Aktif</span>
                                        @elseif ($isTaActive)
                                            @can('semester.activate')
                                                <form action="{{ route('admin.semester.activate', $semGanjil) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-gray-700 shadow-2xs border border-gray-200 hover:bg-gray-50 hover:text-brand-600 transition">Aktifkan</button>
                                                </form>
                                            @endcan
                                        @else
                                            <span class="inline-flex items-center rounded-lg bg-gray-100 px-2 py-1 text-[10px] text-gray-400 cursor-not-allowed" title="Aktifkan Tahun Ajaran {{ $ta->nama }} terlebih dahulu">Aktifkan</span>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            {{-- Item Genap --}}
                            <div class="flex items-center justify-between rounded-xl bg-gray-50/80 border border-gray-100 p-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-gray-800">Genap</span>
                                        @if ($semGenap && $semGenap->kode_dapodik)
                                            <span class="rounded bg-white px-1.5 py-0.5 font-mono text-[10px] font-semibold text-gray-600 border border-gray-200 shadow-2xs">{{ $semGenap->kode_dapodik }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-gray-500">
                                        @if ($semGenap && $semGenap->tanggal_mulai)
                                            {{ \Carbon\Carbon::parse($semGenap->tanggal_mulai)->format('d/m/y') }} - {{ \Carbon\Carbon::parse($semGenap->tanggal_selesai)->format('d/m/y') }}
                                        @else
                                            <span class="italic text-gray-400">Belum dikonfigurasi</span>
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    @if ($semGenap)
                                        @if ($semGenap->status_aktif)
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">Aktif</span>
                                        @elseif ($isTaActive)
                                            @can('semester.activate')
                                                <form action="{{ route('admin.semester.activate', $semGenap) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-gray-700 shadow-2xs border border-gray-200 hover:bg-gray-50 hover:text-brand-600 transition">Aktifkan</button>
                                                </form>
                                            @endcan
                                        @else
                                            <span class="inline-flex items-center rounded-lg bg-gray-100 px-2 py-1 text-[10px] text-gray-400 cursor-not-allowed" title="Aktifkan Tahun Ajaran {{ $ta->nama }} terlebih dahulu">Aktifkan</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Footer status summary --}}
                    <div class="mt-4 flex items-center justify-between text-[11px] text-gray-400 pt-3 border-t border-gray-50">
                        <span>ID: #{{ $ta->id }}</span>
                        <span>{{ $ta->semester->count() }} dari 2 Semester Terdaftar</span>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-2xl border-2 border-dashed border-gray-200 p-12 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                        <x-icon name="calendar_today" class="h-6 w-6" />
                    </div>
                    <h3 class="mt-4 font-display text-sm font-semibold text-gray-900">Belum Ada Tahun Ajaran</h3>
                    <p class="mt-1 text-sm text-gray-500">Mulai dengan menambahkan kalender tahun ajaran pertama Anda.</p>
                    @can('tahun-ajaran.create')
                        <div class="mt-6">
                            <x-primary-button type="button" @click="openCreateTa()">Tambah Tahun Ajaran Baru</x-primary-button>
                        </div>
                    @endcan
                </div>
            @endforelse
        </div>

        {{-- Partials SPA Modals --}}
        @include('admin.tahun-ajaran._modal-tahun-ajaran')
        @include('admin.tahun-ajaran._modal-semester')
    </div>
</x-app-layout>
