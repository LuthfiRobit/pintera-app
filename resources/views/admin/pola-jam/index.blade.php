<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
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
                <x-link-button href="{{ route('admin.pola-jam.create') }}" class="shrink-0 justify-center">
                    <span class="text-base leading-none mr-1.5">+</span> Tambah Pola Jam
                </x-link-button>
                <p class="hidden sm:block text-sm text-gray-500">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pola Jam</b>
                </p>
            </div>
        </div>

        <div class="space-y-6 mt-2">
            @forelse ($polaJamList as $pola)
                @php $hariAktifPola = \App\Enums\Hari::aktifDari($pola->lembaga->hari_libur_mingguan ?? []); @endphp
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    
                    {{-- 1. Card Header: Nama Pola Jam & Aksi --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 bg-white px-6 py-4">
                        <div class="flex items-center gap-3">
                            <h2 class="font-display text-lg font-bold text-gray-900">{{ $pola->nama }}</h2>
                            <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-600 ring-1 ring-inset ring-brand-500/20">{{ $pola->jamPelajaran->count() }} slot</span>
                        </div>

                        <div class="flex items-center gap-4">
                            @can('pola-jam.edit')
                                <a href="{{ route('admin.pola-jam.edit', $pola) }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900 transition">Edit Nama</a>
                            @endcan
                            @can('pola-jam.delete')
                                <form method="POST" action="{{ route('admin.pola-jam.destroy', $pola) }}" x-data @submit.prevent="confirmDialog('Hapus Pola Jam?', @js('Apakah Anda yakin ingin menghapus pola jam \"' . $pola->nama . '\"? Seluruh data yang terkait bisa terpengaruh.'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-semibold text-error-600 hover:text-error-700 transition">Hapus</button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    {{-- 2. Tautkan ke Kelas (Paling Atas) --}}
                    @can('kelas.edit')
                        <div class="border-b border-gray-100 bg-gray-50/30 px-6 py-4">
                            <form method="POST" action="{{ route('admin.pola-jam.assign-kelas', $pola) }}" class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                                @csrf
                                @method('PUT')
                                <div class="flex flex-col md:flex-row md:items-center gap-4 flex-1">
                                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 shrink-0">TAUTKAN KE KELAS:</span>
                                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                                        @foreach ($kelasList as $kelasOpsi)
                                            @php $dipakaiPolaLain = $kelasOpsi->pola_jam_id && $kelasOpsi->pola_jam_id !== $pola->id; @endphp
                                            <label class="group flex items-center gap-2.5 text-sm transition-colors {{ $dipakaiPolaLain ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 font-medium cursor-pointer hover:text-gray-900' }}">
                                                <input
                                                    type="checkbox"
                                                    name="kelas_ids[]"
                                                    value="{{ $kelasOpsi->id }}"
                                                    @checked($kelasOpsi->pola_jam_id === $pola->id)
                                                    @disabled($dipakaiPolaLain)
                                                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-gray-200"
                                                >
                                                <span>{{ $kelasOpsi->nama }}{{ $dipakaiPolaLain ? ' (pola lain)' : '' }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="shrink-0 mt-2 lg:mt-0">
                                    <x-secondary-button type="submit" class="w-full justify-center lg:w-auto">
                                        Simpan Tautan
                                    </x-secondary-button>
                                </div>
                            </form>
                        </div>
                    @endcan

                    {{-- 3. Form Tambah Slot Jam Pelajaran (Urutan Kedua) --}}
                    <div class="border-b border-gray-100 bg-white px-6 py-5">
                        <form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="pola_jam_id" value="{{ $pola->id }}">
                            
                            {{-- Baris 1: Hari (checkbox, bisa pilih beberapa sekaligus) --}}
                            <div>
                                <x-input-label value="Hari" class="mb-2 text-sm text-gray-700" />
                                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                                    @foreach ($hariAktifPola as $hari)
                                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 transition-colors hover:text-gray-900 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                name="hari[]"
                                                value="{{ $hari->value }}"
                                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                                            >
                                            <span>{{ $hari->label() }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Baris 2: Urutan, Jam Mulai, Jam Selesai, Label --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-start">
                                <div class="lg:col-span-3">
                                    <x-input-label value="Urutan" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="number" name="urutan" placeholder="Ke-" min="1" class="block w-full py-2 text-sm shadow-sm" />
                                </div>

                                <div class="lg:col-span-3">
                                    <x-input-label value="Jam Mulai" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="time" name="jam_mulai" class="block w-full py-2 font-mono text-sm shadow-sm" />
                                </div>

                                <div class="lg:col-span-3">
                                    <x-input-label value="Jam Selesai" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="time" name="jam_selesai" class="block w-full py-2 font-mono text-sm shadow-sm" />
                                </div>

                                <div class="lg:col-span-3">
                                    <x-input-label value="Label Slot" class="mb-1 text-sm text-gray-700" />
                                    <x-text-input type="text" name="label" placeholder="mis. Jam ke-1 / Istirahat" class="block w-full py-2 text-sm shadow-sm" />
                                </div>
                            </div>

                            {{-- Baris 2: Jenis Sesi & Tombol Tambah --}}
                            <div class="flex flex-col sm:flex-row items-end gap-4 pt-1">
                                <div class="w-full sm:w-48 lg:w-[16.666667%]">
                                    <x-input-label value="Jenis Sesi" class="mb-1 text-sm text-gray-700" />
                                    <select name="is_pelajaran" class="block w-full rounded-lg border-gray-200 py-2 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                        <option value="1">Jam Belajar</option>
                                        <option value="0">Non-pelajaran</option>
                                    </select>
                                </div>
                                <div class="w-full sm:w-auto">
                                    <x-secondary-button type="submit" class="w-full justify-center sm:w-auto">
                                        Tambah Slot
                                    </x-secondary-button>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- 4. Daftar Jam Pelajaran per Hari (Paling Bawah) --}}
                    <div class="divide-y divide-gray-100 bg-white">
                        @if ($pola->jamPelajaran->isEmpty())
                            <div class="px-6 py-12 text-center text-sm text-gray-500">
                                Belum ada slot jam pelajaran yang didaftarkan.
                            </div>
                        @else
                            @foreach (\App\Enums\Hari::cases() as $hari)
                                @php $slotHariIni = $pola->jamPelajaran->where('hari', $hari)->sortBy('urutan'); @endphp
                                @if ($slotHariIni->isNotEmpty())
                                    <div>
                                        <div class="flex items-center justify-between bg-gray-50/50 px-6 py-3 border-y border-gray-100 mt-[-1px]">
                                            <p class="text-[11px] font-bold uppercase tracking-widest text-gray-600">{{ $hari->label() }}</p>
                                            <span class="text-xs font-medium text-gray-400">{{ $slotHariIni->count() }} sesi</span>
                                        </div>

                                        <ul class="divide-y divide-gray-50">
                                            @foreach ($slotHariIni as $slot)
                                                <li class="flex flex-col md:flex-row md:items-center justify-between gap-4 px-6 py-3.5 transition-colors hover:bg-gray-50/40">
                                                    
                                                    <div class="flex flex-wrap items-center gap-4">
                                                        <div class="flex items-center gap-2 font-mono text-xs">
                                                            <span class="rounded bg-brand-50 px-2 py-1 font-bold text-brand-600 ring-1 ring-inset ring-brand-500/20">{{ $slot->jam_mulai }}</span>
                                                            <span class="text-gray-400 font-medium">&rarr;</span>
                                                            <span class="rounded bg-gray-50 px-2 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-300/50">{{ $slot->jam_selesai }}</span>
                                                        </div>

                                                        <span class="hidden md:inline text-gray-200">&bull;</span>

                                                        <div class="flex items-center gap-3">
                                                            <span class="text-sm font-bold text-gray-800">{{ $slot->label }}</span>
                                                            @if ($slot->is_pelajaran)
                                                                <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-[11px] font-semibold text-success-700 ring-1 ring-inset ring-success-600/20">Belajar</span>
                                                            @else
                                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-600 ring-1 ring-inset ring-gray-400/20">Non-pelajaran</span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center gap-4">
                                                        @can('jam-pelajaran.edit')
                                                            <a href="{{ route('admin.jam-pelajaran.edit', $slot) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-900 transition-colors">Edit</a>
                                                        @endcan
                                                        @can('jam-pelajaran.delete')
                                                            <form method="POST" action="{{ route('admin.jam-pelajaran.destroy', $slot) }}" x-data @submit.prevent="confirmDialog('Hapus Jam Pelajaran?', @js('Apakah Anda yakin ingin menghapus slot \"' . $slot->label . '\"?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="text-xs font-semibold text-error-500 hover:text-error-700 transition-colors">Hapus</button>
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
    </div>
</x-app-layout>