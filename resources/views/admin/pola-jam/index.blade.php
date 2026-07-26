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
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                {{-- Card Header: Nama Pola Jam & Aksi --}}
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 bg-white px-5 py-4">
                    <div class="flex items-center gap-3">
                        <h2 class="font-display text-base font-bold text-gray-900">{{ $pola->nama }}</h2>
                        <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-600 ring-1 ring-inset ring-brand-600/20">{{ $pola->jamPelajaran->count() }} slot</span>
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

                {{-- Daftar Jam Pelajaran per Hari --}}
                <div class="divide-y divide-gray-200/60 bg-white">
                    @foreach (\App\Enums\Hari::cases() as $hari)
                        @php $slotHariIni = $pola->jamPelajaran->where('hari', $hari)->sortBy('urutan'); @endphp
                        @if ($slotHariIni->isNotEmpty())
                            <div>
                                <div class="flex items-center justify-between bg-gray-50/70 px-5 py-2 border-y border-gray-100">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-700">{{ $hari->label() }}</p>
                                    <span class="text-[11px] font-medium text-gray-500">{{ $slotHariIni->count() }} sesi</span>
                                </div>

                                <ul class="divide-y divide-gray-100">
                                    @foreach ($slotHariIni as $slot)
                                        <li class="flex flex-wrap items-center justify-between gap-4 px-5 py-3.5 transition hover:bg-gray-50/50">
                                            <div class="flex flex-wrap items-center gap-3.5">
                                                {{-- Badge Pembeda Waktu --}}
                                                <div class="flex items-center gap-1.5 font-mono text-xs">
                                                    <span class="rounded-md bg-brand-50 px-2.5 py-1 font-bold text-brand-700 ring-1 ring-inset ring-brand-600/20">{{ $slot->jam_mulai }}</span>
                                                    <span class="text-gray-400 font-bold">&rarr;</span>
                                                    <span class="rounded-md bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-400/20">{{ $slot->jam_selesai }}</span>
                                                </div>

                                                <span class="hidden sm:inline text-gray-200">&bull;</span>

                                                {{-- Label & Status --}}
                                                <div class="flex items-center gap-2.5">
                                                    <span class="text-sm font-semibold text-gray-900">{{ $slot->label }}</span>
                                                    @if ($slot->is_pelajaran)
                                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Belajar</span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 ring-1 ring-inset ring-gray-400/20">Non-pelajaran</span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Tombol Aksi --}}
                                            <div class="flex items-center gap-4">
                                                @can('jam-pelajaran.edit')
                                                    <a href="{{ route('admin.jam-pelajaran.edit', $slot) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-900 transition">Edit</a>
                                                @endcan
                                                @can('jam-pelajaran.delete')
                                                    <form method="POST" action="{{ route('admin.jam-pelajaran.destroy', $slot) }}" x-data @submit.prevent="confirmDialog('Hapus Jam Pelajaran?', @js('Apakah Anda yakin ingin menghapus slot \"' . $slot->label . '\" (' . $slot->jam_mulai . '–' . $slot->jam_selesai . ')?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-xs font-semibold text-error-600 hover:text-error-700 transition">Hapus</button>
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
                    <div class="px-5 py-10 text-center text-sm text-gray-500">
                        Belum ada slot jam pelajaran untuk pola ini. Gunakan form di bawah untuk menambahkan.
                    </div>
                @endif

                {{-- Inline Form: Tambah Slot Jam Pelajaran (Persis pola formulir-field di jalur PPDB) --}}
                <form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" class="space-y-3 border-t border-gray-200 bg-gray-50 px-5 py-4">
                    @csrf
                    <input type="hidden" name="pola_jam_id" value="{{ $pola->id }}">
                    
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-full sm:w-36">
                            <x-input-label value="Hari" />
                            <select name="hari" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($hariAktifPola as $hari)
                                    <option value="{{ $hari->value }}">{{ $hari->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-24 sm:w-20">
                            <x-input-label value="Urutan" />
                            <x-text-input type="number" name="urutan" placeholder="Ke-" min="1" class="mt-1.5 w-full text-center" />
                        </div>

                        <div class="w-28">
                            <x-input-label value="Jam Mulai" />
                            <x-text-input type="time" name="jam_mulai" class="mt-1.5 w-full font-mono text-sm" />
                        </div>

                        <div class="w-28">
                            <x-input-label value="Jam Selesai" />
                            <x-text-input type="time" name="jam_selesai" class="mt-1.5 w-full font-mono text-sm" />
                        </div>

                        <div class="min-w-[200px] flex-1">
                            <x-input-label value="Label Slot" />
                            <x-text-input type="text" name="label" placeholder="mis. Jam ke-1 / Istirahat" class="mt-1.5 w-full" />
                        </div>

                        <div class="w-full sm:w-44">
                            <x-input-label value="Jenis Sesi" />
                            <select name="is_pelajaran" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="1">Jam Belajar</option>
                                <option value="0">Non-pelajaran</option>
                            </select>
                        </div>

                        <div class="mt-2 sm:mt-0">
                            <x-secondary-button type="submit">Tambah Slot</x-secondary-button>
                        </div>
                    </div>
                </form>

                {{-- Tautkan ke Kelas --}}
                @can('kelas.edit')
                    <form method="POST" action="{{ route('admin.pola-jam.assign-kelas', $pola) }}" class="border-t border-gray-200 bg-white px-5 py-4">
                        @csrf
                        @method('PUT')
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex flex-wrap items-center gap-4 flex-1">
                                <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Tautkan ke Kelas:</span>
                                <div class="flex flex-wrap gap-x-5 gap-y-2">
                                    @foreach ($kelasList as $kelasOpsi)
                                        @php $dipakaiPolaLain = $kelasOpsi->pola_jam_id && $kelasOpsi->pola_jam_id !== $pola->id; @endphp
                                        <label class="flex items-center gap-2 text-sm transition {{ $dipakaiPolaLain ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 font-medium cursor-pointer hover:text-gray-900' }}">
                                            <input
                                                type="checkbox"
                                                name="kelas_ids[]"
                                                value="{{ $kelasOpsi->id }}"
                                                @checked($kelasOpsi->pola_jam_id === $pola->id)
                                                @disabled($dipakaiPolaLain)
                                                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-gray-100"
                                            >
                                            <span>{{ $kelasOpsi->nama }}{{ $dipakaiPolaLain ? ' (pola lain)' : '' }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="shrink-0">
                                <x-secondary-button type="submit">Simpan Tautan</x-secondary-button>
                            </div>
                        </div>
                    </form>
                @endcan
            </div>
        @endforeach

        @if ($polaJamList->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white px-5 py-12 text-center text-sm text-gray-500 shadow-card">
                Belum ada pola jam yang dibuat. Silakan klik tombol Tambah Pola Jam di atas untuk memulai.
            </div>
        @endif
    </div>
</x-app-layout>
