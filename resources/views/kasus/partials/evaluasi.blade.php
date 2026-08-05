<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-4">
    <p class="font-display text-sm font-bold text-gray-900">Evaluasi Kasus</p>

    @if ($kasus->evaluasi->isNotEmpty())
        <div class="space-y-2">
            @foreach ($kasus->evaluasi as $evaluasi)
                <div class="rounded-lg border border-gray-100 px-3 py-2 space-y-1">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-500">{{ $evaluasi->tanggal->format('d M Y H:i') }}</p>
                        <x-badge tone="{{ $evaluasi->keputusan === 'eskalasi' ? 'red' : ($evaluasi->keputusan === 'selesai' ? 'green' : 'blue') }}">
                            {{ ucfirst($evaluasi->keputusan) }}
                        </x-badge>
                    </div>
                    <p class="text-sm text-gray-900">{{ $evaluasi->catatan }}</p>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-xs text-gray-500 italic">Belum ada evaluasi atau keputusan untuk kasus ini.</p>
    @endif

    @if ($isKonselor && $kasus->status->value === 'berjalan')
        <form method="POST" action="{{ route('kasus.evaluasi.store', $kasus) }}" class="space-y-3 border-t border-gray-100 pt-4">
            @csrf
            <div>
                <x-input-label value="Catatan Evaluasi *" />
                <textarea name="catatan" rows="3" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
            </div>
            <div>
                <x-input-label value="Keputusan *" />
                <select name="keputusan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="lanjut">Lanjut</option>
                    <option value="eskalasi">Eskalasi ke Admin</option>
                    <option value="selesai">Selesai</option>
                </select>
            </div>
            <x-primary-button type="submit">Simpan Evaluasi</x-primary-button>
        </form>
    @endif

    @if ($isTriaseAdmin && $kasus->status->value === 'eskalasi')
        <form method="POST" action="{{ route('kasus.evaluasi.store', $kasus) }}" class="space-y-3 border-t border-gray-100 pt-4">
            @csrf
            <div>
                <x-input-label value="Catatan Evaluasi *" />
                <textarea name="catatan" rows="3" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
            </div>
            <div>
                <x-input-label value="Keputusan *" />
                <select name="keputusan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="lanjut">Kembalikan ke Konselor (Lanjut)</option>
                    <option value="selesai">Selesai</option>
                </select>
            </div>
            <x-primary-button type="submit">Simpan Evaluasi</x-primary-button>
        </form>
    @endif
</div>
