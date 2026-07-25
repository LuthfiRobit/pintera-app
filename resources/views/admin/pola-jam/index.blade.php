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
            <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
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

                @foreach (\App\Enums\Hari::cases() as $hari)
                    @php $slotHariIni = $pola->jamPelajaran->where('hari', $hari)->sortBy('urutan'); @endphp
                    @if ($slotHariIni->isNotEmpty())
                        <div class="border-b border-gray-100 px-5 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $hari->label() }}</p>
                            <ul class="mt-1.5 space-y-1">
                                @foreach ($slotHariIni as $slot)
                                    <li class="flex items-center gap-2 text-sm text-gray-700">
                                        {{ $slot->jam_mulai }}–{{ $slot->jam_selesai }} &middot; {{ $slot->label }}
                                        @unless ($slot->is_pelajaran)
                                            <x-badge tone="slate">Non-pelajaran</x-badge>
                                        @endunless
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
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach

                @if ($pola->jamPelajaran->isEmpty())
                    <div class="px-5 py-4 text-sm text-gray-500">Belum ada jam pelajaran untuk pola ini.</div>
                @endif

                <form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" class="flex flex-wrap items-end gap-2 rounded-b-2xl bg-gray-50 px-5 py-4">
                    @csrf
                    <input type="hidden" name="pola_jam_id" value="{{ $pola->id }}">
                    <select name="hari" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($hariAktifPola as $hari)
                            <option value="{{ $hari->value }}">{{ $hari->label() }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="urutan" placeholder="Urutan" min="1" class="w-24 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <input type="text" name="label" placeholder="Label (Jam ke-1, Istirahat, ...)" class="w-48 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <input type="time" name="jam_mulai" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <input type="time" name="jam_selesai" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <select name="is_pelajaran" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="1">Jam Belajar</option>
                        <option value="0">Non-belajar (istirahat/upacara/sholat)</option>
                    </select>
                    <x-primary-button type="submit">Tambah Slot</x-primary-button>
                </form>

                @can('kelas.edit')
                    <form method="POST" action="{{ route('admin.pola-jam.assign-kelas', ['polaJam' => $pola, 'kelas' => '__KELAS__']) }}" class="flex flex-wrap items-center gap-2 border-t border-gray-100 px-5 py-3" onsubmit="this.action = this.action.replace('__KELAS__', this.kelas_id.value); return this.kelas_id.value !== '';">
                        @csrf
                        @method('PUT')
                        <span class="text-sm text-gray-600">Tautkan ke kelas:</span>
                        <select name="kelas_id" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Kelas —</option>
                            @foreach ($kelasList as $kelasOpsi)
                                <option value="{{ $kelasOpsi->id }}" @selected($kelasOpsi->pola_jam_id === $pola->id)>{{ $kelasOpsi->nama }}{{ $kelasOpsi->pola_jam_id && $kelasOpsi->pola_jam_id !== $pola->id ? ' (sudah pakai pola lain)' : '' }}</option>
                            @endforeach
                        </select>
                        <x-primary-button type="submit">Tautkan</x-primary-button>
                    </form>
                @endcan
            </div>
        @endforeach

        @if ($polaJamList->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white px-5 py-10 text-center text-sm text-gray-500 shadow-card">
                Belum ada pola jam.
            </div>
        @endif
    </div>
</x-app-layout>
