{{-- resources/views/keuangan/dashboard.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Dompet &amp; Tagihan — {{ $activeSiswa->nama_lengkap }}</h2>
    </x-slot>

    <div class="space-y-6">
        @if ($skipAlert !== null)
            <div class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-display text-sm font-bold text-amber-800">Saldo tidak cukup untuk {{ $skipAlert['tagihan']->jenisTagihan->nama }}</p>
                    <p class="mt-1 text-sm text-amber-700">Kekurangan Rp{{ number_format($skipAlert['selisih'], 0, ',', '.') }} agar tagihan prioritas tertinggi ini bisa terbayar otomatis.</p>
                </div>
                <a href="{{ route('keuangan.tagihan.index') }}" class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700">
                    Top-up Rp{{ number_format($skipAlert['selisih'], 0, ',', '.') }} Sekarang
                </a>
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <p class="text-sm text-gray-500">Saldo Wallet</p>
            <p class="mt-1 font-display text-3xl font-bold text-gray-900">Rp{{ number_format($wallet?->balance ?? 0, 0, ',', '.') }}</p>
            @if ($wallet?->va_number)
                <p class="mt-2 text-sm text-gray-500">No. VA: <span class="font-mono">{{ $wallet->va_number }}</span></p>
            @endif
            <a href="{{ route('keuangan.tagihan.index') }}" class="mt-4 inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                + Top Up
            </a>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-6"
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
            <div class="flex items-center justify-between">
                <p class="font-display text-sm font-bold text-gray-900">Notifikasi Terbaru</p>
                <button type="button" x-show="unreadCount > 0" @click="tandaiSemua()" class="text-xs font-semibold text-brand-600 hover:text-brand-700" style="display: none;">Tandai semua terbaca</button>
            </div>
            @if ($notificationFeed->isEmpty())
                <p class="mt-3 text-sm text-gray-500">Belum ada notifikasi.</p>
            @else
                <div class="mt-3 divide-y divide-gray-100">
                    @foreach ($notificationFeed as $notification)
                        <button
                            type="button"
                            @click="tandaiSatu('{{ $notification->id }}')"
                            class="w-full py-3 text-left transition hover:bg-gray-50"
                        >
                            <p class="text-sm text-gray-800">{{ $notification->data['message'] ?? '-' }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
