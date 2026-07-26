<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Pola Jam &amp; Jam Pelajaran</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pola Jam</b>
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">
                Kelola jadwal waktu belajar harian dan tautkan dengan kelas yang relevan.
            </p>
            <x-link-button href="{{ route('admin.pola-jam.create') }}">
                <span class="text-base leading-none mr-1">+</span> Tambah Pola Jam
            </x-link-button>
        </div>

        @foreach ($polaJamList as $pola)
            @php $hariAktifPola = \App\Enums\Hari::aktifDari($pola->lembaga->hari_libur_mingguan ?? []); @endphp
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card transition duration-200 hover:shadow-elevated">
                {{-- Card Header: Nama Pola Jam & Aksi --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-200 bg-white px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 border border-brand-100">
                            <x-icon name="schedule" class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="font-display text-base font-bold text-gray-900">{{ $pola->nama }}</h2>
                            <p class="text-xs text-gray-500">Total: {{ $pola->jamPelajaran->count() }} slot jam pelajaran</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 self-end sm:self-center">
                        @can('pola-jam.edit')
                            <a href="{{ route('admin.pola-jam.edit', $pola) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50 hover:text-brand-600">
                                <x-icon name="edit" class="h-3.5 w-3.5 text-gray-400" />
                                Edit Nama
                            </a>
                        @endcan
                        @can('pola-jam.delete')
                            <form method="POST" action="{{ route('admin.pola-jam.destroy', $pola) }}" x-data @submit.prevent="confirmDialog('Hapus Pola Jam?', @js('Apakah Anda yakin ingin menghapus pola jam \"' . $pola->nama . '\"? Seluruh data yang terkait bisa terpengaruh.'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-error-200 bg-error-50 px-3 py-1.5 text-xs font-semibold text-error-600 shadow-2xs transition hover:bg-error-100 hover:text-error-700">
                                    <x-icon name="delete" class="h-3.5 w-3.5" />
                                    Hapus
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>

                {{-- Daftar Jam Pelajaran per Hari --}}
                <div class="divide-y divide-gray-100 bg-white">
                    @foreach (\App\Enums\Hari::cases() as $hari)
                        @php $slotHariIni = $pola->jamPelajaran->where('hari', $hari)->sortBy('urutan'); @endphp
                        @if ($slotHariIni->isNotEmpty())
                            <div class="p-4 sm:px-6 sm:py-5">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block h-2 w-2 rounded-full bg-brand-500"></span>
                                    <p class="text-xs font-bold uppercase tracking-wider text-brand-700">{{ $hari->label() }}</p>
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600">{{ $slotHariIni->count() }} sesi</span>
                                </div>

                                <ul class="mt-3.5 grid grid-cols-1 gap-2.5 sm:gap-3">
                                    @foreach ($slotHariIni as $slot)
                                        <li class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-gray-200/80 bg-gray-50/40 p-3 sm:px-4 sm:py-2.5 transition duration-150 hover:border-gray-300 hover:bg-white hover:shadow-xs">
                                            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                                {{-- Badge Pembeda Jam Mulai & Jam Selesai --}}
                                                <div class="inline-flex items-center gap-1 font-mono text-xs shadow-2xs rounded-lg border border-gray-200 bg-white px-2 py-1">
                                                    <span class="rounded bg-brand-50 px-1.5 py-0.5 font-bold text-brand-700 border border-brand-100">{{ $slot->jam_mulai }}</span>
                                                    <span class="px-0.5 text-[10px] font-semibold uppercase text-gray-400">s.d.</span>
                                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-semibold text-gray-700 border border-gray-200">{{ $slot->jam_selesai }}</span>
                                                </div>

                                                <div class="hidden sm:block text-gray-300">&bull;</div>

                                                {{-- Label & Jenis Sesi --}}
                                                <div class="flex items-center gap-2">
                                                    <span class="font-semibold text-gray-900 text-sm">{{ $slot->label }}</span>
                                                    @if ($slot->is_pelajaran)
                                                        <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-[11px] font-semibold text-success-700 border border-success-200/60">Belajar</span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 border border-slate-200/60">Non-pelajaran</span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Tombol Aksi per Slot --}}
                                            <div class="flex items-center justify-end gap-2 self-end sm:self-auto border-t sm:border-0 pt-2 sm:pt-0 w-full sm:w-auto border-gray-200/60">
                                                @can('jam-pelajaran.edit')
                                                    <a href="{{ route('admin.jam-pelajaran.edit', $slot) }}" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-600 shadow-2xs hover:bg-gray-50 hover:text-gray-900">
                                                        Edit
                                                    </a>
                                                @endcan
                                                @can('jam-pelajaran.delete')
                                                    <form method="POST" action="{{ route('admin.jam-pelajaran.destroy', $slot) }}" x-data @submit.prevent="confirmDialog('Hapus Jam Pelajaran?', @js('Apakah Anda yakin ingin menghapus slot jam \"' . $slot->label . '\" (' . $slot->jam_mulai . ' - ' . $slot->jam_selesai . ')?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-error-200 bg-error-50 px-2.5 py-1 text-xs font-semibold text-error-600 shadow-2xs hover:bg-error-100 hover:text-error-700">
                                                            Hapus
                                                        </button>
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

                @if ($pola->jamPelajaran->isEmpty())
                    <div class="px-5 py-8 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                            <x-icon name="schedule" class="h-6 w-6" />
                        </div>
                        <p class="mt-2 text-sm font-semibold text-gray-700">Belum Ada Slot Jam Pelajaran</p>
                        <p class="text-xs text-gray-500">Gunakan form di bawah untuk mulai menata slot jam belajar pada pola ini.</p>
                    </div>
                @endif

                {{-- Inline Form: Tambah Slot Jam Pelajaran (Desain Kompak & Ergonomis) --}}
                <div class="border-t border-gray-200 bg-gray-50 p-4 sm:p-5">
                    <p class="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-gray-600">
                        <x-icon name="add_circle" class="h-4 w-4 text-brand-600" />
                        Tambah Slot Jam Pelajaran
                    </p>
                    
                    <form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="pola_jam_id" value="{{ $pola->id }}">
                        
                        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4 lg:grid-cols-12">
                            <div class="col-span-1 sm:col-span-1 lg:col-span-2">
                                <x-input-label value="Hari" class="mb-1 text-[11px]" />
                                <select name="hari" class="h-[38px] w-full rounded-lg border-gray-200 py-1.5 pl-2.5 pr-8 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                                    @foreach ($hariAktifPola as $hari)
                                        <option value="{{ $hari->value }}">{{ $hari->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-span-1 sm:col-span-1 lg:col-span-1">
                                <x-input-label value="Urutan" class="mb-1 text-[11px]" />
                                <input type="number" name="urutan" placeholder="Ke-" min="1" class="h-[38px] w-full rounded-lg border-gray-200 px-2.5 py-1.5 text-center text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            <div class="col-span-2 sm:col-span-2 lg:col-span-3">
                                <x-input-label value="Waktu (Mulai &ndash; Selesai)" class="mb-1 text-[11px]" />
                                <div class="flex items-center gap-1.5">
                                    <input type="time" name="jam_mulai" class="h-[38px] w-full rounded-lg border-gray-200 px-2 py-1.5 font-mono text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                                    <span class="text-xs font-bold text-gray-400">&ndash;</span>
                                    <input type="time" name="jam_selesai" class="h-[38px] w-full rounded-lg border-gray-200 px-2 py-1.5 font-mono text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                                </div>
                            </div>

                            <div class="col-span-2 sm:col-span-2 lg:col-span-3">
                                <x-input-label value="Label Slot" class="mb-1 text-[11px]" />
                                <input type="text" name="label" placeholder="mis. Jam ke-1 / Istirahat / Sholat" class="h-[38px] w-full rounded-lg border-gray-200 px-3 py-1.5 text-xs text-gray-900 placeholder:text-gray-400 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            <div class="col-span-2 sm:col-span-2 lg:col-span-3">
                                <x-input-label value="Jenis Sesi" class="mb-1 text-[11px]" />
                                <select name="is_pelajaran" class="h-[38px] w-full rounded-lg border-gray-200 py-1.5 pl-2.5 pr-8 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                                    <option value="1">Jam Belajar (Pelajaran)</option>
                                    <option value="0">Non-pelajaran (Istirahat/Upacara)</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-end pt-1">
                            <x-secondary-button type="submit" class="h-[36px] px-4 text-xs font-semibold">
                                <span class="mr-1 text-sm font-bold leading-none">+</span> Tambah Slot
                            </x-secondary-button>
                        </div>
                    </form>
                </div>

                {{-- Tautkan ke Kelas --}}
                @can('kelas.edit')
                    <form method="POST" action="{{ route('admin.pola-jam.assign-kelas', $pola) }}" class="border-t border-gray-200 bg-white px-5 py-4">
                        @csrf
                        @method('PUT')
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-gray-700">Tautkan ke Kelas</p>
                                <p class="text-xs text-gray-500 mt-0.5">Pilih kelas yang menggunakan susunan dan pola jam ini.</p>
                            </div>
                            <x-secondary-button type="submit" class="text-xs font-semibold">
                                Simpan Tautan Kelas
                            </x-secondary-button>
                        </div>

                        <div class="mt-3.5 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                            @foreach ($kelasList as $kelasOpsi)
                                @php $dipakaiPolaLain = $kelasOpsi->pola_jam_id && $kelasOpsi->pola_jam_id !== $pola->id; @endphp
                                <label class="flex items-center gap-2.5 rounded-lg border px-3 py-2 text-xs transition {{ $kelasOpsi->pola_jam_id === $pola->id ? 'border-brand-300 bg-brand-50/50 font-semibold text-brand-900' : ($dipakaiPolaLain ? 'border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed' : 'border-gray-200 bg-white font-medium text-gray-700 hover:bg-gray-50 cursor-pointer') }}">
                                    <input
                                        type="checkbox"
                                        name="kelas_ids[]"
                                        value="{{ $kelasOpsi->id }}"
                                        @checked($kelasOpsi->pola_jam_id === $pola->id)
                                        @disabled($dipakaiPolaLain)
                                        class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 disabled:cursor-not-allowed"
                                    >
                                    <span class="truncate">{{ $kelasOpsi->nama }}</span>
                                    @if ($dipakaiPolaLain)
                                        <span class="text-[10px] text-gray-400">(pola lain)</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </form>
                @endcan
            </div>
        @endforeach

        @if ($polaJamList->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white px-5 py-12 text-center text-sm text-gray-500 shadow-card">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                    <x-icon name="schedule" class="h-7 w-7" />
                </div>
                <p class="mt-3 font-display text-base font-bold text-gray-800">Belum Ada Pola Jam</p>
                <p class="mt-1 text-sm text-gray-500">Silakan tambahkan pola jam pertama untuk mulai mengatur waktu belajar kelas Anda.</p>
            </div>
        @endif
    </div>
</x-app-layout>
