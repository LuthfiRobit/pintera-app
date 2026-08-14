{{-- resources/views/keuangan/dashboard.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0" x-data="{ selected: [], showTopUpModal: false }">
        
        {{-- Header & Subtitle (Inline style matching admin/kasus/index.blade.php) --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Dompet &amp; Tagihan</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola saldo dompet, notifikasi terbaru, dan lunasi tagihan aktif {{ $activeSiswa->nama_lengkap }}.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Keuangan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Dashboard</b>
            </p>
        </div>

        {{-- Main Responsive Grid --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            
            {{-- Left Column (Saldo + VA Card, Notifications) --}}
            <div class="space-y-6 lg:col-span-5">
                
                {{-- My Card (Sleek Dark Mode Glassmorphism) --}}
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-gray-900 via-gray-800 to-black p-6 text-white shadow-lg transition hover:shadow-xl">
                    {{-- Decorative lines/shapes in the background --}}
                    <div class="absolute -right-16 -top-16 h-40 w-40 rounded-full bg-brand-500/10 blur-3xl"></div>
                    <div class="absolute -bottom-20 -left-20 h-48 w-48 rounded-full bg-indigo-500/10 blur-3xl"></div>
                    
                    {{-- Card Header --}}
                    <div class="flex items-center justify-between">
                        <span class="font-display text-xs font-bold uppercase tracking-widest text-gray-400">Pintera Wallet</span>
                        {{-- Gold Chip / Logo Icon --}}
                        <svg class="h-8 w-8 text-amber-400" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="3" y="6" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                            <rect x="6" y="9" width="4" height="6" rx="1"/>
                            <line x1="14" y1="9" x2="18" y2="9" stroke="currentColor" stroke-width="2"/>
                            <line x1="14" y1="12" x2="18" y2="12" stroke="currentColor" stroke-width="2"/>
                            <line x1="14" y1="15" x2="18" y2="15" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    
                    {{-- Card Number representation / VA Number --}}
                    <div class="mt-8 font-mono text-lg tracking-widest text-gray-200">
                        @if ($wallet?->va_number)
                            {{ implode(' ', str_split($wallet->va_number, 4)) }}
                        @else
                            •••• •••• •••• ••••
                        @endif
                    </div>
                    
                    {{-- Balance Display --}}
                    <div class="mt-6">
                        <p class="text-[10px] uppercase tracking-wider text-gray-400">Total Balance</p>
                        <p class="font-display text-3xl font-bold tracking-tight text-white mt-1">
                            Rp{{ number_format($wallet?->balance ?? 0, 0, ',', '.') }}
                        </p>
                    </div>

                    {{-- Student / Card Holder Info --}}
                    <div class="mt-6 flex items-end justify-between">
                        <div>
                            <p class="text-[9px] uppercase tracking-wider text-gray-400">Card Holder</p>
                            <p class="font-display text-xs font-bold text-gray-100 truncate max-w-[180px]">{{ $activeSiswa->nama_lengkap }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] uppercase tracking-wider text-gray-400">Status</p>
                            <span class="inline-flex items-center gap-1 rounded bg-green-500/20 px-1.5 py-0.5 text-[9px] font-semibold text-green-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-400 animate-pulse"></span> Aktif
                            </span>
                        </div>
                    </div>
                </div>

                {{-- VA & Quick Actions Card --}}
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Metode Top Up</p>
                            <p class="text-sm font-bold text-gray-700 mt-1">Virtual Account (VA)</p>
                        </div>
                        <button type="button" @click="showTopUpModal = true" class="inline-flex items-center justify-center gap-1 rounded-xl px-3 py-2 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 transition">
                            + Top Up Saldo
                        </button>
                    </div>
                    
                    @if ($wallet?->va_number)
                        <div class="mt-4 flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3 border border-gray-100">
                            <div>
                                <p class="text-[10px] font-semibold text-gray-400 uppercase">Nomor Virtual Account</p>
                                <p class="font-mono text-sm font-bold text-gray-800 mt-0.5">{{ $wallet->va_number }}</p>
                            </div>
                            <button
                                @click="
                                    navigator.clipboard.writeText('{{ $wallet->va_number }}');
                                    $store.toast.push('success', 'Nomor VA berhasil disalin!');
                                "
                                type="button"
                                class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition"
                                title="Salin VA"
                                aria-label="Salin VA"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Notifications Feed --}}
                <div
                    class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4"
                    x-data="{
                        readIds: [],
                        unreadCount: {{ $notificationFeed->whereNull('read_at')->count() }},
                        async tandaiSatu(id) {
                            if (this.readIds.includes(id)) return;
                            const response = await fetch(`{{ url('/notifikasi') }}/${id}/baca`, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                            });
                            if (!response.ok) return;
                            this.readIds.push(id);
                            const data = await response.json();
                            this.unreadCount = data.unread_count;
                        },
                        async tandaiSemua() {
                            const response = await fetch('{{ route('notifikasi.baca-semua') }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                            });
                            if (!response.ok) return;
                            this.readIds = @js($notificationFeed->pluck('id')->all());
                            this.unreadCount = 0;
                        }
                    }"
                >
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                            </span>
                            <p class="font-display text-sm font-bold text-gray-900">Notifikasi Terbaru</p>
                        </div>
                        <button type="button" x-show="unreadCount > 0" @click="tandaiSemua()" class="text-xs font-semibold text-brand-600 hover:text-brand-700" style="display: none;">Tandai semua terbaca</button>
                    </div>

                    @if ($notificationFeed->isEmpty())
                        <p class="py-4 text-center text-xs text-gray-400">Belum ada notifikasi baru.</p>
                    @else
                        <div class="divide-y divide-gray-100 max-h-[300px] overflow-y-auto pr-1">
                            @foreach ($notificationFeed as $notification)
                                <button
                                    type="button"
                                    @click="tandaiSatu('{{ $notification->id }}')"
                                    class="w-full py-3 text-left transition hover:bg-gray-50/50 block group focus:outline-none"
                                    :class="readIds.includes('{{ $notification->id }}') ? 'opacity-60' : ''"
                                >
                                    <div class="flex items-start gap-3">
                                        {{-- Read/unread indicator dot --}}
                                        <span 
                                            x-show="!readIds.includes('{{ $notification->id }}')" 
                                            class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-600"
                                            @if (!is_null($notification->read_at)) style="display: none;" @endif
                                        ></span>
                                        <div class="flex-1">
                                            <p class="text-xs text-gray-700 leading-normal group-hover:text-brand-900 transition-colors">{{ $notification->data['message'] ?? '-' }}</p>
                                            <p class="mt-1 text-[10px] text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right Column (Tagihan Aktif, Auto Debit, Skip Alerts) --}}
            <div class="space-y-6 lg:col-span-7">
                
                {{-- Alerts & Top-up Reminders --}}
                @if ($skipAlert !== null)
                    <div class="flex flex-col gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800 mt-0.5">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </span>
                            <div>
                                <p class="font-display text-sm font-bold text-amber-800">Saldo kurang untuk autodebit prioritas</p>
                                <p class="mt-1 text-xs text-amber-700 leading-normal">
                                    Kekurangan sebesar <b class="font-bold">Rp{{ number_format($skipAlert['selisih'], 0, ',', '.') }}</b> agar tagihan prioritas tertinggi <span class="font-semibold">({{ $skipAlert['tagihan']->jenisTagihan->nama }})</span> dapat didebit otomatis.
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('keuangan.tagihan.index') }}" class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-amber-700 shrink-0">
                            Top Up Rp{{ number_format($skipAlert['selisih'], 0, ',', '.') }}
                        </a>
                    </div>
                @endif

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

                {{-- Billing Card (List and payment select) --}}
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </span>
                            <p class="font-display text-sm font-bold text-gray-900">Tagihan Aktif Belum Lunas</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-semibold text-gray-600" x-text="`${selected.length} dipilih`"></span>
                    </div>

                    @if ($tagihans->isEmpty())
                        <div class="text-center py-8">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-green-500 mx-auto mb-3">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <p class="text-sm font-semibold text-gray-700">Semua Tagihan Lunas!</p>
                            <p class="text-xs text-gray-400 mt-1">Tidak ada tagihan tertunggak untuk siswa ini.</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach ($tagihans as $tagihan)
                                <label class="flex items-start gap-4 py-4 cursor-pointer hover:bg-gray-50/50 transition-colors px-2 rounded-xl">
                                    <input 
                                        type="checkbox" 
                                        value="{{ $tagihan->id }}" 
                                        x-model="selected" 
                                        class="mt-1 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                    >
                                    <div class="flex-1">
                                        <p class="text-xs font-semibold text-gray-900">{{ $tagihan->jenisTagihan->nama }}</p>
                                        <p class="text-[10px] text-gray-400 mt-0.5">Jatuh tempo: {{ $tagihan->jatuh_tempo?->translatedFormat('d M Y') ?? '-' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-bold text-gray-900">Rp{{ number_format($tagihan->net_amount - $tagihan->paid_amount, 0, ',', '.') }}</p>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-bold tracking-wider uppercase mt-1 {{ $tagihan->status === 'sebagian' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800' }}">
                                            {{ str_replace('_', ' ', $tagihan->status) }}
                                        </span>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        {{-- Payment Action --}}
                        <div x-show="selected.length > 0" x-cloak class="mt-6 flex items-center justify-end border-t border-gray-100 pt-4" style="display: none;">
                            <a :href="`{{ route('keuangan.checkout.create') }}?` + selected.map(id => `tagihan_ids[]=${id}`).join('&')"
                               class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 w-full sm:w-auto text-center gap-1.5">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                                Bayar Terpilih (<span x-text="selected.length"></span>)
                            </a>
                        </div>
                    @endif
                </div>

            </div>
        </div>
        {{-- Modal Top Up Saldo --}}
        <div x-show="showTopUpModal"
             class="fixed inset-0 z-50 overflow-y-auto"
             x-cloak
             role="dialog"
             aria-modal="true"
             @keydown.escape.window="showTopUpModal = false"
             style="display: none;">
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" 
                 x-show="showTopUpModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="showTopUpModal = false">
            </div>

            {{-- Modal Content Wrapper --}}
            <div class="flex min-h-full items-center justify-center p-4 text-center">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all w-full max-w-lg"
                     x-show="showTopUpModal"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                    
                    {{-- Header --}}
                    <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <h3 class="font-display text-sm font-bold text-gray-900">Cara Top Up Saldo Wallet</h3>
                        </div>
                        <button @click="showTopUpModal = false" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-50 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">
                        @if ($wallet?->va_number)
                            <div class="rounded-xl bg-gray-50 p-4 border border-gray-100 flex items-center justify-between">
                                <div>
                                    <p class="text-[9px] font-semibold text-gray-400 uppercase tracking-wider">Nomor Virtual Account (BRIVA) Anda</p>
                                    <p class="font-mono text-base font-bold text-gray-800 mt-1">{{ $wallet->va_number }}</p>
                                    <p class="text-[10px] text-gray-400 mt-1">Nama Pemilik: {{ $activeSiswa->nama_lengkap }}</p>
                                </div>
                                <button
                                    @click="
                                        navigator.clipboard.writeText('{{ $wallet->va_number }}');
                                        $store.toast.push('success', 'Nomor VA berhasil disalin!');
                                    "
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition shadow-sm"
                                    title="Salin VA"
                                    aria-label="Salin VA"
                                >
                                    <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                    </svg>
                                    <span>Salin VA</span>
                                </button>
                            </div>
                        @endif

                        {{-- Instructions --}}
                        <div class="space-y-4 text-xs sm:text-sm text-gray-600">
                            {{-- BRIMo --}}
                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-900 flex items-center gap-2">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-blue-50 text-blue-600 text-[10px] font-bold">1</span>
                                    Melalui Aplikasi BRImo (Mobile Banking)
                                </h4>
                                <ol class="list-decimal pl-5 space-y-1 text-gray-500 text-[11px] leading-relaxed">
                                    <li>Buka dan login ke aplikasi <b>BRImo</b>.</li>
                                    <li>Pilih menu <b>BRIVA</b>.</li>
                                    <li>Klik <b>Pembayaran Baru</b>.</li>
                                    <li>Masukkan nomor <b>BRIVA tujuan</b> di atas.</li>
                                    <li>Periksa detail nama dan tagihan, lalu masukkan nominal top up.</li>
                                    <li>Masukkan <b>PIN</b> untuk konfirmasi.</li>
                                </ol>
                            </div>

                            {{-- ATM --}}
                            <div class="space-y-2">
                                <h4 class="font-bold text-gray-900 flex items-center gap-2">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-blue-50 text-blue-600 text-[10px] font-bold">2</span>
                                    Melalui ATM BRI
                                </h4>
                                <ol class="list-decimal pl-5 space-y-1 text-gray-500 text-[11px] leading-relaxed">
                                    <li>Masukkan kartu debit dan <b>PIN ATM</b> Anda.</li>
                                    <li>Pilih menu <b>Transaksi Lain</b>.</li>
                                    <li>Pilih <b>Pembayaran</b>, lalu klik <b>Lainnya</b>.</li>
                                    <li>Pilih <b>BRIVA</b>.</li>
                                    <li>Masukkan nomor <b>BRIVA</b> di atas.</li>
                                    <li>Periksa layar konfirmasi nama pemilik, lalu tekan <b>Ya</b>.</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="bg-gray-50/50 px-6 py-4 flex items-center justify-end border-t border-gray-100">
                        <button @click="showTopUpModal = false" type="button" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition">
                            Saya Mengerti
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
