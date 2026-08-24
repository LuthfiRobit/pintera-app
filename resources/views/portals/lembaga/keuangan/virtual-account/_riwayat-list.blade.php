@if ($logs->isEmpty())
    <p class="text-sm text-gray-400 text-center py-6">Belum ada pembayaran masuk lewat VA ini.</p>
@else
    <table class="w-full text-left text-xs">
        <thead>
            <tr class="text-[10px] uppercase tracking-wider text-gray-400 border-b border-gray-100">
                <th class="py-2">Tanggal</th>
                <th class="py-2 text-right">Nominal</th>
                <th class="py-2">Referensi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach ($logs as $log)
                <tr>
                    <td class="py-2 text-gray-600">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                    <td class="py-2 text-right font-mono font-semibold text-gray-800">Rp{{ number_format($log->amount, 0, ',', '.') }}</td>
                    <td class="py-2 font-mono text-gray-500">{{ $log->payment_request_id }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
