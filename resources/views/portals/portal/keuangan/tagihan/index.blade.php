{{-- resources/views/keuangan/tagihan/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0"
         x-data="{
             selected: [],
             activeFilter: 'semua',
             detailUrlTemplate: '{{ route('keuangan.tagihan.show', ['tagihan' => '__ID__']) }}',
             detailUrl(id) { return this.detailUrlTemplate.replace('__ID__', id); },
             items: @js($tagihans->map(fn($t) => [
                 'id' => $t->id,
                 'nama' => $t->jenisTagihan->nama,
                 'jatuh_tempo_raw' => $t->jatuh_tempo?->toDateString(),
                 'jatuh_tempo_formatted' => $t->jatuh_tempo?->translatedFormat('d M Y') ?? '-',
                 'is_overdue' => $t->jatuh_tempo ? $t->jatuh_tempo->isPast() : false,
                 'net_amount' => (float) $t->net_amount,
                 'paid_amount' => (float) $t->paid_amount,
                 'sisa' => (float) ($t->net_amount - $t->paid_amount),
                 'status' => $t->status,
                 'perlu_ditinjau_ulang' => (bool) $t->perlu_ditinjau_ulang,
                 'badge_tone' => $t->perlu_ditinjau_ulang ? 'bg-amber-50 text-amber-700 border-amber-200' : ($t->status === 'sebagian' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-red-50 text-red-700 border-red-200'),
                 'badge_label' => $t->perlu_ditinjau_ulang ? 'sedang ditinjau' : str_replace('_', ' ', $t->status)
             ])->values()->all()),

             get filteredItems() {
                 if (this.activeFilter === 'jatuh_tempo') {
                     return this.items.filter(item => item.is_overdue);
                 }
                 return this.items;
             },

             get selectableItems() {
                 return this.filteredItems.filter(item => !item.perlu_ditinjau_ulang);
             },
             
             get countSemua() {
                 return this.items.length;
             },
             
             get countJatuhTempo() {
                 return this.items.filter(item => item.is_overdue).length;
             }
         }"
    >
        
        {{-- Header & Subtitle (Inline style matching admin/kasus/index.blade.php) --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Rekap Tagihan Aktif</h1>
                <p class="text-xs text-gray-500 mt-0.5">Lihat rekap komponen biaya sekolah dan lunasi tagihan {{ $activeSiswa->nama_lengkap }} secara mandiri.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Keuangan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tagihan</b>
            </p>
        </div>

        {{-- Billing Engine & Auto-debit Info --}}
        @if ($autoDebitEnabled)
            <div class="flex items-start gap-3 rounded-2xl border border-brand-200 bg-brand-50/50 p-4 text-xs text-brand-800">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </span>
                <div>
                    <p class="font-semibold text-brand-900">Sistem Auto-Debit Aktif</p>
                    <p class="mt-0.5 text-gray-500 leading-relaxed">Setiap kali saldo wallet didepositkan (top-up), sistem akan langsung mendebit tagihan prioritas secara otomatis. Anda tetap dapat mencicil/membayar tagihan pilihan Anda secara instan di bawah ini.</p>
                </div>
            </div>
        @endif

        {{-- Filter Tabs (Matching admin/kasus/index.blade.php style) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-500">Filter Status Tagihan</label>
                <div class="flex items-center gap-2 overflow-x-auto scrollbar-none pb-1 sm:pb-0">
                    {{-- Tab: Semua --}}
                    <button @click="activeFilter = 'semua'; selected = [];" type="button" :class="activeFilter === 'semua' ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-sm' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" class="flex items-center gap-1.5 justify-center px-4 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap">
                        <span>Semua Tagihan</span>
                        <span :class="activeFilter === 'semua' ? 'bg-brand-100/80 text-brand-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countSemua"></span>
                    </button>
                    {{-- Tab: Jatuh Tempo --}}
                    <button @click="activeFilter = 'jatuh_tempo'; selected = [];" type="button" :class="activeFilter === 'jatuh_tempo' ? 'bg-red-50 font-semibold text-red-700 border-red-200 shadow-sm' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'" class="flex items-center gap-1.5 justify-center px-4 py-2 rounded-lg text-xs font-semibold border transition-all whitespace-nowrap">
                        <span>Jatuh Tempo (Menunggak)</span>
                        <span :class="activeFilter === 'jatuh_tempo' ? 'bg-red-100/80 text-red-700' : 'bg-gray-200 text-gray-700'" class="px-2 py-0.5 text-[10px] rounded-full font-bold" x-text="countJatuhTempo"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Billing Card (Detailed Table layout) --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            {{-- Table Header --}}
            <div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <p class="font-display text-sm font-bold text-gray-900" x-text="activeFilter === 'jatuh_tempo' ? 'Daftar Tagihan Jatuh Tempo' : 'Daftar Tagihan Tertunggak'"></p>
                    <span class="inline-flex items-center rounded-full bg-brand-50 border border-brand-200 px-2.5 py-0.5 text-xs font-semibold text-brand-700" x-text="`${filteredItems.length} Tagihan`"></span>
                </div>
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-semibold text-gray-600" x-text="`${selected.length} dipilih`"></span>
            </div>

            <template x-if="filteredItems.length === 0">
                <div class="text-center py-12">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-green-500 mx-auto mb-3">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <p class="text-sm font-semibold text-gray-700" x-text="activeFilter === 'jatuh_tempo' ? 'Tidak Ada Tagihan Jatuh Tempo' : 'Semua Tagihan Lunas!'"></p>
                    <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto" x-text="activeFilter === 'jatuh_tempo' ? 'Hebat! Siswa ini tidak memiliki tunggakan biaya sekolah yang melewati jatuh tempo.' : 'Tidak ada tagihan aktif tertunggak saat ini.'"></p>
                </div>
            </template>

            <template x-if="filteredItems.length > 0">
                <div>
                    {{-- Table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100 bg-gray-50/50">
                                    <th class="px-5 py-3 w-12 text-center">
                                        <input type="checkbox"
                                               @change="selected = $el.checked ? selectableItems.map(i => i.id) : []"
                                               :checked="selectableItems.length > 0 && selected.length === selectableItems.length"
                                               class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 cursor-pointer"
                                        >
                                    </th>
                                    <th class="px-5 py-3">Tagihan</th>
                                    <th class="px-5 py-3">Jatuh Tempo</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Nominal Tagihan</th>
                                    <th class="px-5 py-3 text-right">Sudah Dibayar</th>
                                    <th class="px-5 py-3 text-right">Sisa Tagihan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="item in filteredItems" :key="item.id">
                                    <tr class="transition hover:bg-gray-50" :class="item.is_overdue ? 'bg-red-50/10' : ''">
                                        <td class="px-5 py-3 text-center">
                                            <input type="checkbox" :value="item.id" x-model="selected" :disabled="item.perlu_ditinjau_ulang" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 cursor-pointer disabled:cursor-not-allowed">
                                        </td>
                                        <td class="px-5 py-3.5 font-medium text-gray-900">
                                            <a :href="detailUrl(item.id)" class="hover:text-brand-600 hover:underline" x-text="item.nama"></a>
                                            <p x-show="item.perlu_ditinjau_ulang" class="text-[10px] font-normal text-amber-600 mt-0.5">Nominal sedang ditinjau ulang oleh admin, sementara belum bisa dibayar.</p>
                                        </td>
                                        <td class="px-5 py-3.5 text-gray-600 font-mono text-xs">
                                            <span x-text="item.jatuh_tempo_formatted"></span>
                                            <span x-show="item.is_overdue" class="ml-1.5 inline-flex items-center rounded bg-red-100 px-1.5 py-0.5 text-[8px] font-bold text-red-800 uppercase tracking-wider">Terlambat</span>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <span :class="item.badge_tone" class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-[10px]" x-text="item.badge_label"></span>
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-medium text-gray-500 font-mono text-xs" x-text="'Rp' + new Intl.NumberFormat('id-ID').format(item.net_amount)"></td>
                                        <td class="px-5 py-3.5 text-right font-medium text-emerald-600 font-mono text-xs" x-text="'Rp' + new Intl.NumberFormat('id-ID').format(item.paid_amount)"></td>
                                        <td class="px-5 py-3.5 text-right font-bold text-gray-900 font-mono text-xs" x-text="'Rp' + new Intl.NumberFormat('id-ID').format(item.sisa)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Payment Action --}}
                    <div x-show="selected.length > 0" x-cloak class="border-t border-gray-200 px-5 py-4 flex items-center justify-end" style="display: none;">
                        <a :href="`{{ route('keuangan.checkout.create') }}?` + selected.map(id => `tagihan_ids[]=${id}`).join('&')"
                           class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 w-full sm:w-auto text-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            Bayar Terpilih (<span x-text="selected.length"></span>)
                        </a>
                    </div>
                </div>
            </template>
        </div>

    </div>
</x-app-layout>
