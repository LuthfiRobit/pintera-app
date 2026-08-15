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
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500">
                    <th class="sticky left-0 z-10 bg-white px-5 py-3.5">Aksi</th>
                    <th class="px-5 py-3.5">Nama Siswa</th>
                    <th class="px-5 py-3.5">Kelas</th>
                    <th class="px-5 py-3.5">Nomor VA</th>
                    <th class="px-5 py-3.5 text-right">Saldo Wallet</th>
                    <th class="px-5 py-3.5">Tanggal Dibuat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($vaList as $va)
                    <tr class="transition hover:bg-gray-50/80">
                        <td class="sticky left-0 z-10 bg-white px-5 py-4 shadow-[1px_0_0_0_#f3f4f6]">
                            <x-table-actions>
                                <x-dropdown-link href="#" @click.prevent="$dispatch('open-riwayat-modal', { siswaId: {{ $va->wallet->siswa_id }}, siswaNama: @js($va->wallet->siswa->nama_lengkap ?? '-') })">
                                    Lihat Riwayat
                                </x-dropdown-link>
                                <x-dropdown-link href="#" @click.prevent="$dispatch('open-topup-modal', { siswaId: {{ $va->wallet->siswa_id }}, siswaNama: @js($va->wallet->siswa->nama_lengkap ?? '-'), vaNumber: @js($va->va_number), balance: {{ (float)$va->wallet->balance }} })">
                                    Top-up Saldo
                                </x-dropdown-link>
                            </x-table-actions>
                        </td>
                        <td class="px-5 py-4">
                            <p class="font-bold text-gray-900 leading-tight">{{ $va->wallet->siswa->nama_lengkap ?? '-' }}</p>
                            <p class="font-mono text-xs text-gray-400 mt-0.5">NIS: {{ $va->wallet->siswa->nis ?? '-' }}</p>
                        </td>
                        <td class="px-5 py-4 font-medium text-gray-600">{{ $va->wallet->siswa->kelas->nama ?? '-' }}</td>
                        <td class="px-5 py-4 font-mono text-xs font-semibold text-gray-700">{{ $va->va_number }}</td>
                        <td class="px-5 py-4 text-right font-mono text-xs font-semibold text-gray-900">Rp{{ number_format($va->wallet->balance, 0, ',', '.') }}</td>
                        <td class="px-5 py-4 text-xs text-gray-500">{{ $va->created_at->translatedFormat('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-sm font-medium text-gray-500">
                            Belum ada siswa dengan nomor Virtual Account.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($vaList->hasPages())
        <div class="border-t border-gray-200 bg-gray-50/50 px-5 py-3">
            {{ $vaList->links() }}
        </div>
    @endif
</div>
