<div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Menunggu Verifikasi</p>
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
                    <th class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3 w-40">Aksi</th>
                    <th class="px-4 py-3">Nama Siswa</th>
                    <th class="px-4 py-3 text-right">Nominal</th>
                    <th class="px-4 py-3">Jenis</th>
                    <th class="px-4 py-3">Tanggal Transfer</th>
                    <th class="px-4 py-3">Bukti</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($requestList as $req)
                    <tr class="transition-colors hover:bg-gray-50/60">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('admin.manual-payment.approve', $req) }}" onsubmit="return confirm('Setujui transfer manual ini? Uang akan langsung diproses.');">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">Setujui</button>
                                </form>
                                <button type="button" x-data @click="$dispatch('open-reject-modal', { id: {{ $req->id }} })" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">Tolak</button>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 font-medium text-gray-900">{{ $req->pembayaran->siswa->nama_lengkap ?? '-' }}</td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs font-semibold text-gray-700">Rp{{ number_format($req->amount, 0, ',', '.') }}</td>
                        <td class="px-4 py-3.5 text-xs text-gray-600">
                            @if ($req->pembayaran->topup_status !== 'none' && $req->pembayaran->pembayaranTagihan->isEmpty())
                                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">Top Up Wallet</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Bayar Tagihan</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-xs text-gray-600">{{ \Illuminate\Support\Carbon::parse($req->transfer_date)->translatedFormat('d M Y') }}</td>
                        <td class="px-4 py-3.5 text-xs">
                            <a href="{{ Illuminate\Support\Facades\Storage::disk('public')->url($req->transfer_proof_path) }}" target="_blank" class="font-semibold text-brand-600 hover:text-brand-700">Lihat Bukti</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-500">
                            <p class="text-sm">Tidak ada permintaan yang menunggu verifikasi.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($requestList->hasPages())
        <div class="border-t border-gray-200 px-5 py-3">
            {{ $requestList->links('pagination.tailadmin') }}
        </div>
    @endif
</div>

<div
    x-data="{ open: false, requestId: null, reason: '' }"
    x-on:open-reject-modal.window="open = true; requestId = $event.detail.id; reason = ''"
    x-show="open"
    style="display: none;"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
>
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated" @click.outside="open = false">
        <h3 class="font-display text-sm font-bold text-gray-900">Alasan Penolakan</h3>
        <form method="POST" :action="requestId ? `{{ url('admin/manual-payment') }}/${requestId}/reject` : '#'" class="mt-4 space-y-3">
            @csrf
            <textarea name="rejection_reason" x-model="reason" required maxlength="255" rows="4" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Jelaskan alasan penolakan..."></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Batal</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700">Tolak Permintaan</button>
            </div>
        </form>
    </div>
</div>
