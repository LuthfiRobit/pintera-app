<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-lg font-bold text-gray-900">Sinkronisasi Kurikulum Kelas</h1>
                    @if ($isPlatformOrYayasan ?? false)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                            <x-icon name="apartment" class="h-3.5 w-3.5" />
                            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                        </span>
                    @endif
                </div>
                <p class="mt-0.5 text-xs text-gray-500">Alat koreksi manual untuk kelas yang kurikulum/fase tersimpannya sudah tidak sesuai dengan aturan kurikulum terbaru. Tidak ada yang berubah otomatis -- pilih kelas yang mau disinkronkan.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.kurikulum-assignment.index') }}" class="hover:text-gray-700">Pengaturan Kurikulum</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span>
                <b class="font-semibold text-gray-700">Sinkronisasi</b>
            </p>
        </div>

        <form method="GET" action="{{ route('admin.kurikulum-assignment.resync') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            @if ($activeLembaga)
                <div class="w-full sm:w-64">
                    <x-input-label value="Lembaga" />
                    <div class="mt-1.5 flex h-[42px] items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3.5 text-sm font-medium text-gray-700">
                        <x-icon name="apartment" class="h-4 w-4 shrink-0 text-gray-400" />
                        <span class="truncate">{{ $activeLembaga->nama }}</span>
                    </div>
                    <input type="hidden" name="lembaga_id" value="{{ $activeLembaga->id }}">
                </div>
            @elseif ($isPlatformOrYayasan)
                <div class="w-full sm:w-64">
                    <x-input-label value="Lembaga" />
                    <x-select name="lembaga_id" class="mt-1.5" onchange="this.form.submit()">
                        <option value="">— Pilih Lembaga —</option>
                        @foreach ($lembagaList as $l)
                            <option value="{{ $l->id }}" @selected($lembagaId === $l->id)>{{ $l->nama }}</option>
                        @endforeach
                    </x-select>
                </div>
            @else
                <input type="hidden" name="lembaga_id" value="{{ $lembagaId }}">
            @endif

            <div class="w-full sm:w-64">
                <x-input-label value="Tahun Ajaran" />
                <x-select name="tahun_ajaran_id" class="mt-1.5">
                    <option value="">— Pilih Tahun Ajaran —</option>
                    @foreach ($tahunAjaranList as $ta)
                        <option value="{{ $ta->id }}" @selected($tahunAjaranId === $ta->id)>{{ $ta->nama }}</option>
                    @endforeach
                </x-select>
            </div>
            <x-primary-button type="submit" class="h-[42px]">Pindai Keselarasan</x-primary-button>
        </form>

        @if ($lembagaId !== null && $tahunAjaranId !== null)
            <form
                method="POST"
                action="{{ route('admin.kurikulum-assignment.resync.apply') }}"
                class="rounded-2xl border border-gray-200 bg-white shadow-sm"
                x-data="{ terpilih: [] }"
                @submit.prevent="confirmDialog(
                    'Sinkronkan Kurikulum/Fase Kelas Terpilih?',
                    `Kurikulum dan fase pada ${terpilih.length} kelas terpilih akan diperbarui ke aturan terbaru. Pastikan guru dan wali kelas sudah mengetahui perubahan ini sebelum melanjutkan.`,
                    { confirmLabel: 'Ya, Sinkronkan Sekarang', isDanger: false }
                ).then(confirmed => { if (confirmed) $el.submit() })"
            >
                @csrf
                <input type="hidden" name="lembaga_id" value="{{ $lembagaId }}">
                <input type="hidden" name="tahun_ajaran_id" value="{{ $tahunAjaranId }}">

                @if (empty($diff))
                    <div class="flex flex-col items-center justify-center gap-2 p-10 text-center">
                        <x-icon name="check_circle" class="h-8 w-8 text-success-500" />
                        <p class="font-display text-sm font-bold text-gray-900">Semua Kelas Sudah Selaras</p>
                        <p class="text-xs text-gray-500">Tidak ada kelas di kombinasi ini yang perlu disinkronkan.</p>
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">
                                    <input type="checkbox" @click="terpilih = $event.target.checked ? @js(collect($diff)->pluck('kelas.id')->map(fn ($v) => (string) $v)->all()) : []" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 transition cursor-pointer">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kelas</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kurikulum: Lama → Seharusnya</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Fase: Lama → Seharusnya</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($diff as $row)
                                <tr>
                                    <td class="px-4 py-3">
                                        <input type="checkbox" name="kelas_ids[]" value="{{ $row['kelas']->id }}" x-model="terpilih" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 transition cursor-pointer">
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['kelas']->nama }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $row['kurikulumLama'] ?? '—' }}</span>
                                            <x-icon name="arrow_forward" class="h-3.5 w-3.5 text-gray-300" />
                                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">{{ $row['kurikulumBaru'] ?? '—' }}</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $row['faseLamaNama'] ?? 'Tanpa Fase' }}</span>
                                            <x-icon name="arrow_forward" class="h-3.5 w-3.5 text-gray-300" />
                                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">{{ $row['faseBaruNama'] ?? 'Tanpa Fase' }}</span>
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div x-show="terpilih.length > 0" x-cloak x-transition class="sticky bottom-0 flex items-center justify-between gap-3 border-t border-gray-200 bg-white/95 px-5 py-3.5 backdrop-blur">
                        <p class="text-xs font-medium text-gray-600"><span x-text="terpilih.length"></span> kelas dipilih untuk disinkronkan</p>
                        <button type="submit" :disabled="terpilih.length === 0" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">Terapkan Sinkronisasi</button>
                    </div>
                @endif
            </form>
        @else
            <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center">
                <x-icon name="sync" class="h-10 w-10 text-gray-300" />
                <p class="mt-3 font-display text-sm font-bold text-gray-900">Pilih Lembaga &amp; Tahun Ajaran untuk Memindai</p>
                <p class="mt-1 max-w-sm text-xs text-gray-500">Sistem akan memeriksa apakah ada kelas yang kurikulum atau fasenya berbeda dari aturan kurikulum terbaru.</p>
            </div>
        @endif
    </div>
</x-app-layout>
