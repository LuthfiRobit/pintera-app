<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tagihan Perlu Ditinjau Ulang</h2>
            </div>
            <a
                href="{{ route('admin.tagihan.index') }}"
                class="inline-flex items-center gap-2 rounded-xl border border-ink/15 px-3.5 py-2 text-xs font-semibold text-ink hover:bg-paper"
            >
                <x-icon name="arrow_back" class="h-4 w-4" />
                Kembali ke Daftar Tagihan
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-signal-emerald/20 bg-signal-emerald/10 p-4 text-sm font-medium text-signal-emerald">
                {{ session('status') }}
            </div>
        @endif

        <x-panel>
            @if ($tagihanList->isEmpty())
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <x-icon name="task_alt" class="h-12 w-12 text-signal-emerald" />
                    <p class="mt-3 font-display text-base font-semibold text-ink">Semua Tagihan Bersih</p>
                    <p class="mt-1 text-sm text-slate">Tidak ada tagihan yang memerlukan peninjauan ulang saat ini.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                                <th class="px-5 py-3 font-display font-semibold">Subjek Tagihan</th>
                                <th class="px-5 py-3 font-display font-semibold">Jenis Tagihan</th>
                                <th class="px-5 py-3 font-display font-semibold">Nominal / Terbayar</th>
                                <th class="px-5 py-3 font-display font-semibold">Alasan Peninjauan</th>
                                <th class="px-5 py-3 text-right font-display font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/10">
                            @foreach ($tagihanList as $tagihan)
                                <tr class="hover:bg-paper/30 transition">
                                    <td class="px-5 py-4">
                                        <p class="font-medium text-ink">
                                            @if ($tagihan->tagihable_type === \App\Models\Siswa::class)
                                                {{ $tagihan->tagihable?->nama_lengkap ?? 'Siswa #'.$tagihan->tagihable_id }}
                                            @else
                                                {{ $tagihan->pendaftaran?->calonMurid?->nama_lengkap ?? 'Pendaftaran #'.$tagihan->pendaftaran_id }}
                                            @endif
                                        </p>
                                        <p class="font-mono text-xs text-slate">
                                            @if ($tagihan->tagihable_type === \App\Models\Siswa::class)
                                                NISN: {{ $tagihan->tagihable?->nisn ?? '-' }}
                                            @else
                                                Kode: {{ $tagihan->pendaftaran?->kode_pendaftaran ?? '-' }}
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-5 py-4 text-ink">
                                        <p class="font-medium">{{ $tagihan->jenisTagihan?->nama ?? ucfirst($tagihan->kategori?->value ?? '-') }}</p>
                                        <p class="text-xs text-slate">{{ $tagihan->billing_period ? 'Periode: '.$tagihan->billing_period : 'Sekali Bayar' }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-ink">
                                        <p class="font-medium">Rp {{ number_format((float) $tagihan->net_amount, 0, ',', '.') }}</p>
                                        <p class="text-xs text-slate">Dibayar: Rp {{ number_format((float) $tagihan->paid_amount, 0, ',', '.') }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="inline-flex items-start gap-1.5 rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs text-amber-800 border border-amber-200/60">
                                            <x-icon name="warning" class="h-4 w-4 shrink-0 text-amber-600 mt-0.5" />
                                            <span>{{ $tagihan->alasan_perlu_ditinjau ?? 'Perlu ditinjau ulang oleh admin.' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex flex-col items-end gap-2">
                                            <div class="flex items-center justify-end gap-2">
                                                <form action="{{ route('admin.tagihan.selesai-ditinjau', $tagihan) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-1.5 rounded-xl bg-ink px-3 py-1.5 text-xs font-semibold text-paper shadow-sm hover:bg-ink/90 transition"
                                                        onclick="return confirm('Tandai tagihan ini telah selesai ditinjau?');"
                                                    >
                                                        <x-icon name="check" class="h-3.5 w-3.5" />
                                                        Selesai Ditinjau
                                                    </button>
                                                </form>

                                                <div x-data="{ open: false }" class="relative inline-block">
                                                    <button
                                                        type="button"
                                                        @click="open = !open"
                                                        class="inline-flex items-center gap-1.5 rounded-xl border border-ink/15 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-paper transition"
                                                    >
                                                        <x-icon name="edit" class="h-3.5 w-3.5" />
                                                        Koreksi Nominal
                                                    </button>

                                                    <div
                                                        x-show="open"
                                                        x-cloak
                                                        @click.outside="open = false"
                                                        class="absolute z-10 mt-2 w-64 rounded-xl border border-ink/10 bg-white p-3 text-left shadow-elevated"
                                                    >
                                                        <form action="{{ route('admin.tagihan.koreksi-nominal', $tagihan) }}" method="POST" class="space-y-2">
                                                            @csrf
                                                            <div>
                                                                <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate">Total Tagihan</label>
                                                                <input
                                                                    type="number"
                                                                    name="total_tagihan"
                                                                    value="{{ (int) $tagihan->total_tagihan }}"
                                                                    min="0"
                                                                    class="w-full rounded-lg border-ink/15 text-xs focus:border-ink focus:ring-ink"
                                                                    required
                                                                >
                                                            </div>
                                                            <div>
                                                                <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate">Potongan</label>
                                                                <input
                                                                    type="number"
                                                                    name="discount_amount"
                                                                    value="{{ (int) $tagihan->discount_amount }}"
                                                                    min="0"
                                                                    class="w-full rounded-lg border-ink/15 text-xs focus:border-ink focus:ring-ink"
                                                                    required
                                                                >
                                                            </div>
                                                            <button
                                                                type="submit"
                                                                class="w-full rounded-lg bg-ink px-3 py-1.5 text-xs font-semibold text-paper hover:bg-ink/90 transition"
                                                                onclick="return confirm('Terapkan koreksi nominal ini? Perubahan akan langsung berlaku.');"
                                                            >
                                                                Terapkan Koreksi
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($tagihanList->hasPages())
                    <div class="border-t border-ink/10 p-4">
                        {{ $tagihanList->links() }}
                    </div>
                @endif
            @endif
        </x-panel>
    </div>
</x-app-layout>
