<div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Virtual Account</p>
        <div class="flex items-center gap-2">
            <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
            <select id="per_page" x-model="perPage" @change="muatUlangDaftar()" class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
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
                    <th class="px-4 py-3">Nama Siswa</th>
                    <th class="px-4 py-3">Kelas</th>
                    <th class="px-4 py-3">Nomor VA</th>
                    <th class="px-4 py-3 text-right">Saldo Wallet</th>
                    <th class="px-4 py-3">Tanggal Dibuat</th>
                    <th class="px-4 py-3 w-32">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($vaList as $va)
                    <tr class="transition-colors hover:bg-gray-50/60">
                        <td class="px-4 py-3.5 font-medium text-gray-900">{{ $va->wallet->siswa->nama_lengkap ?? '-' }}</td>
                        <td class="px-4 py-3.5 text-xs text-gray-600">{{ $va->wallet->siswa->kelas->nama ?? '-' }}</td>
                        <td class="px-4 py-3.5 font-mono text-xs font-semibold text-gray-700">{{ $va->va_number }}</td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs font-semibold text-gray-700">Rp{{ number_format($va->wallet->balance, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 text-xs text-gray-600">{{ $va->created_at->translatedFormat('d M Y') }}</td>
                        <td class="px-4 py-3.5">
                            <button type="button" x-data @click="$dispatch('open-riwayat-modal', { siswaId: {{ $va->wallet->siswa_id }}, siswaNama: @js($va->wallet->siswa->nama_lengkap ?? '-') })" class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100">Lihat Riwayat</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-500">
                            <p class="text-sm">Belum ada siswa dengan nomor Virtual Account.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($vaList->hasPages())
        <div class="border-t border-gray-200 px-5 py-3">
            {{ $vaList->links('pagination.tailadmin') }}
        </div>
    @endif
</div>
