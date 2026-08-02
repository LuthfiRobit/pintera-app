<div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
    {{-- Minimalist Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Mata Pelajaran</p>
        <div class="flex items-center gap-2">
            <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
            <select
                id="per_page"
                x-model="perPage"
                @change="muatUlangDaftar()"
                class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500"
            >
                @foreach ([10, 20, 25, 50] as $n)
                    <option value="{{ $n }}" @selected(($perPage ?? 20) == $n)>{{ $n }} / hal</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50/75 font-display text-xs font-bold uppercase tracking-wider text-gray-500">
                    <th class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3 text-center w-28">Aksi</th>
                    <th class="px-4 py-3 text-center w-20">No. Rapor</th>
                    <th class="px-4 py-3 w-32">Kode</th>
                    <th class="px-4 py-3">Nama Mata Pelajaran</th>
                    <th class="px-4 py-3">Tipe</th>
                    <th class="px-4 py-3">Kelompok</th>
                    <th class="px-5 py-3 text-center w-28">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($mataPelajaranList as $mapel)
                    <tr class="transition-colors hover:bg-gray-50/60">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3.5 text-center group-hover:bg-gray-50/60">
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('admin.mata-pelajaran.edit', $mapel) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-gray-200 bg-white px-2.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 hover:text-brand-600">
                                    Edit
                                </a>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-center font-mono text-xs font-bold text-gray-600">
                            {{ $mapel->no_urut }}
                        </td>
                        <td class="px-4 py-3.5 font-mono text-xs font-semibold text-brand-600">
                            {{ $mapel->kode }}
                        </td>
                        <td class="px-4 py-3.5 font-medium text-gray-900">
                            {{ $mapel->nama }}
                        </td>
                        <td class="px-4 py-3.5 text-xs text-gray-600">
                            {{ $mapel->tipe->label() }}
                        </td>
                        <td class="px-4 py-3.5 text-xs text-gray-600">
                            {{ $mapel->kelompok?->label() ?? '—' }}
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if ($mapel->status === \App\Enums\StatusMataPelajaran::Aktif)
                                <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700">Aktif</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colSpan="7" class="px-5 py-12 text-center text-gray-500">
                            <p class="text-sm">Belum ada mata pelajaran yang didaftarkan.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($mataPelajaranList->hasPages())
        <div class="border-t border-gray-200 px-5 py-3">
            {{ $mataPelajaranList->links('pagination.tailadmin') }}
        </div>
    @endif
</div>
