<x-app-layout>
    <div class="mx-auto max-w-lg space-y-6">
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">Kehadiran Saya</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Ajukan Izin/Cuti</h1>
        </div>

        <form method="POST" action="{{ route('sdm.izin-cuti.store') }}" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            @csrf
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kategori</label>
                <select name="kategori" required class="w-full rounded-lg border-gray-200 text-sm">
                    @foreach ($kategoriOptions as $kategori)
                        <option value="{{ $kategori->value }}">{{ $kategori->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" required class="w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Selesai</label>
                    <input type="date" name="tanggal_selesai" required class="w-full rounded-lg border-gray-200 text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Alasan</label>
                <textarea name="alasan" rows="3" required class="w-full rounded-lg border-gray-200 text-sm"></textarea>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                <a href="{{ route('sdm.izin-cuti.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Kirim Pengajuan</button>
            </div>
        </form>
    </div>
</x-app-layout>
