<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Pola Jam &amp; Jam Pelajaran</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pola Jam</b>
            </p>
        </div>

        <div class="flex justify-end">
            <x-link-button href="{{ route('admin.pola-jam.create') }}">
                <span class="text-base leading-none">+</span> Tambah Pola Jam
            </x-link-button>
        </div>

        @foreach ($polaJamList as $pola)
            @php $hariAktifPola = \App\Enums\Hari::aktifDari($pola->lembaga->hari_libur_mingguan ?? []); @endphp
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                {{-- Card Header: Nama Pola Jam & Aksi --}}
                <div class="flex items-center justify-between border-b border-gray-200 bg-white px-5 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">{{ $pola->nama }}</p>
                    <div class="flex items-center gap-3">
                        @can('pola-jam.edit')
                            <a href="{{ route('admin.pola-jam.edit', $pola) }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">Edit</a>
                        @endcan
                        @can('pola-jam.delete')
                            <form method="POST" action="{{ route('admin.pola-jam.destroy', $pola) }}" onsubmit="return confirm('Hapus pola jam ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
                            </form>
                        @endcan
                    </div>
                </div>

                {{-- Daftar Jam Pelajaran per Hari --}}
                <div class="divide-y divide-gray-100 bg-white">
                    @foreach (\App\Enums\Hari::cases() as $hari)
                        @php $slotHariIni = $pola->jamPelajaran->where('hari', $hari)->sortBy('urutan'); @endphp
                        @if ($slotHariIni->isNotEmpty())
                            <div class="px-5 py-3.5">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $hari->label() }}</p>
                                <ul class="mt-2 space-y-1.5">
                                    @foreach ($slotHariIni as $slot)
                                        <li class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 bg-gray-50/50 px-3.5 py-2 text-sm text-gray-700 transition hover:bg-gray-50">
                                            <div class="flex items-center gap-2.5 font-medium">
                                                <span class="font-mono text-xs font-semibold text-gray-500">{{ $slot->jam_mulai }}–{{ $slot->jam_selesai }}</span>
                                                <span class="text-gray-300">&bull;</span>
                                                <span class="text-gray-900">{{ $slot->label }}</span>
                                                @unless ($slot->is_pelajaran)
                                                    <x-badge tone="slate">Non-pelajaran</x-badge>
                                                @endunless
                                            </div>
                                            <div class="flex items-center gap-2.5">
                                                @can('jam-pelajaran.edit')
                                                    <a href="{{ route('admin.jam-pelajaran.edit', $slot) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Edit</a>
                                                @endcan
                                                @can('jam-pelajaran.delete')
                                                    <form method="POST" action="{{ route('admin.jam-pelajaran.destroy', $slot) }}" onsubmit="return confirm('Hapus jam pelajaran ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-xs font-semibold text-error-600 hover:text-error-700">Hapus</button>
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
                    <div class="px-5 py-6 text-center text-sm text-gray-500">Belum ada jam pelajaran untuk pola ini.</div>
                @endif

                {{-- Inline Form: Tambah Slot Jam Pelajaran (Desain Bersih & Berlabel) --}}
                <div class="border-t border-gray-200 bg-gray-50/70 px-5 py-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Tambah Slot Jam Pelajaran</p>
                    <form method="POST" action="{{ route('admin.jam-pelajaran.store') }}">
                        @csrf
                        <input type="hidden" name="pola_jam_id" value="{{ $pola->id }}">
                        
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12">
                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-gray-600">Hari</label>
                                <select name="hari" class="w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    @foreach ($hariAktifPola as $hari)
                                        <option value="{{ $hari->value }}">{{ $hari->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-gray-600">Urutan</label>
                                <input type="number" name="urutan" placeholder="Contoh: 1" min="1" class="w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div class="sm:col-span-2 lg:col-span-4">
                                <label class="mb-1 block text-xs font-semibold text-gray-600">Label</label>
                                <input type="text" name="label" placeholder="Jam ke-1, Istirahat, ..." class="w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-gray-600">Jam Mulai</label>
                                <input type="time" name="jam_mulai" class="w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-gray-600">Jam Selesai</label>
                                <input type="time" name="jam_selesai" class="w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
                            <div class="w-full sm:w-auto sm:min-w-64">
                                <label class="mb-1 block text-xs font-semibold text-gray-600">Jenis Jam</label>
                                <select name="is_pelajaran" class="w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="1">Jam Belajar</option>
                                    <option value="0">Non-belajar (istirahat / upacara / sholat)</option>
                                </select>
                            </div>
                            <div>
                                <x-primary-button type="submit" class="w-full sm:w-auto justify-center">
                                    <span class="text-sm leading-none mr-1.5">+</span> Tambah Slot
                                </x-primary-button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Tautkan ke Kelas --}}
                @can('kelas.edit')
                    <form method="POST" action="{{ route('admin.pola-jam.assign-kelas', $pola) }}" class="border-t border-gray-200 bg-white px-5 py-4">
                        @csrf
                        @method('PUT')
                        <p class="mb-2.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Tautkan ke Kelas</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($kelasList as $kelasOpsi)
                                @php $dipakaiPolaLain = $kelasOpsi->pola_jam_id && $kelasOpsi->pola_jam_id !== $pola->id; @endphp
                                <label class="flex items-center gap-2 text-sm {{ $dipakaiPolaLain ? 'text-gray-400' : 'text-gray-700 font-medium' }}">
                                    <input
                                        type="checkbox"
                                        name="kelas_ids[]"
                                        value="{{ $kelasOpsi->id }}"
                                        @checked($kelasOpsi->pola_jam_id === $pola->id)
                                        @disabled($dipakaiPolaLain)
                                        class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed"
                                    >
                                    {{ $kelasOpsi->nama }}{{ $dipakaiPolaLain ? ' (pakai pola lain)' : '' }}
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-3.5">
                            <x-primary-button type="submit" variant="secondary">Simpan Tautan Kelas</x-primary-button>
                        </div>
                    </form>
                @endcan
            </div>
        @endforeach

        @if ($polaJamList->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white px-5 py-12 text-center text-sm text-gray-500 shadow-card">
                Belum ada pola jam yang dibuat.
            </div>
        @endif
    </div>
</x-app-layout>
