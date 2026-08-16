<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4" x-data="{ openMutasi: false }">
        {{-- Flash Messages --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="font-display text-lg font-bold text-gray-900">{{ $aset->nama_barang }}</h1>
                    <x-badge tone="slate">{{ $aset->kode_inventaris }}</x-badge>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">Kategori: {{ $aset->kategori->nama_kategori ?? '-' }} &bull; Lokasi: {{ $aset->ruangan->nama_ruangan ?? '-' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <x-link-button variant="secondary" href="{{ route('admin.sarpras.aset.index') }}">
                    <x-icon name="arrow_back" class="h-4 w-4" /> Kembali
                </x-link-button>
                <button type="button" @click="openMutasi = true" class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-purple-700 transition">
                    <x-icon name="swap_horiz" class="h-4 w-4" /> Mutasi Ruangan
                </button>
                <x-link-button href="{{ route('admin.sarpras.aset.edit', $aset) }}">
                    <x-icon name="edit" class="h-4 w-4" /> Edit
                </x-link-button>
            </div>
        </div>

        {{-- Detail Grid --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="md:col-span-2 rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Informasi Pokok Barang</p>
                
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Merk / Brand</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ $aset->merk ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Tipe Pencatatan</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ $aset->tipe_pencatatan->label() }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Kuantitas / Satuan</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ $aset->qty }} {{ $aset->satuan }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Kondisi Fisik</p>
                        <span class="inline-block mt-0.5">
                            <x-badge tone="{{ $aset->kondisi->value === 'baik' ? 'green' : ($aset->kondisi->value === 'rusak_ringan' ? 'amber' : 'rose') }}">
                                {{ $aset->kondisi->label() }}
                            </x-badge>
                        </span>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Sumber Perolehan</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ $aset->sumber_perolehan->label() }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Harga Perolehan</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ $aset->harga_perolehan ? 'Rp ' . number_format($aset->harga_perolehan, 0, ',', '.') : '-' }}</p>
                    </div>
                </div>

                @if($aset->spesifikasi)
                    <div class="pt-3 border-t border-gray-100 text-xs">
                        <p class="text-gray-400 font-semibold uppercase text-[10px]">Spesifikasi Detail</p>
                        <p class="text-gray-700 mt-1 whitespace-pre-line leading-relaxed">{{ $aset->spesifikasi }}</p>
                    </div>
                @endif
            </div>

            <div class="md:col-span-1 rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <p class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Foto Fisik Barang</p>
                @if($aset->foto_barang_path)
                    <img src="{{ asset('storage/' . $aset->foto_barang_path) }}" alt="{{ $aset->nama_barang }}" class="w-full h-48 object-cover rounded-xl border border-gray-200">
                @else
                    <div class="flex flex-col items-center justify-center h-48 rounded-xl bg-gray-50 border border-dashed border-gray-200 text-gray-400 text-xs">
                        <x-icon name="image" class="h-8 w-8 mb-2 text-gray-300" />
                        Foto belum diunggah
                    </div>
                @endif
            </div>
        </div>

        {{-- Riwayat Mutasi Table --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card overflow-hidden">
            <div class="p-5 border-b border-gray-100">
                <p class="font-display text-sm font-bold text-gray-900">Riwayat Mutasi & Perpindahan Lokasi</p>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Dari Ruangan</th>
                        <th class="px-5 py-3">Ke Ruangan</th>
                        <th class="px-5 py-3 text-center">Jumlah</th>
                        <th class="px-5 py-3">Alasan</th>
                        <th class="px-5 py-3">Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium">
                    @forelse($aset->riwayatMutasi as $m)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-5 py-3.5 text-gray-900">{{ $m->tanggal_mutasi->translatedFormat('d M Y') }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $m->ruanganAsal->nama_ruangan ?? '-' }}</td>
                            <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $m->ruanganTujuan->nama_ruangan ?? '-' }}</td>
                            <td class="px-5 py-3.5 text-center">{{ $m->qty_pindah }} unit</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $m->alasan_mutasi }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ $m->dilakukanOleh->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-gray-400">Aset ini belum pernah dipindahkan lokasinya.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Modal Dialog Mutasi --}}
        <div x-show="openMutasi" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" style="display: none;" x-transition.opacity>
            <div @click.away="openMutasi = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <p class="font-display text-sm font-bold text-gray-900">Mutasi / Pindah Ruangan Aset</p>
                    <button type="button" @click="openMutasi = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
                </div>

                <form action="{{ route('admin.sarpras.mutasi.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="aset_barang_id" value="{{ $aset->id }}">

                    <div class="rounded-xl bg-gray-50 p-3 text-xs text-gray-600 space-y-1">
                        <p><strong>Lokasi Saat Ini:</strong> {{ $aset->ruangan->nama_ruangan ?? '-' }}</p>
                        <p><strong>Stok Tersedia:</strong> {{ $aset->qty }} {{ $aset->satuan }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Pindahkan Ke Ruangan <span class="text-rose-500">*</span></label>
                        <select name="ruangan_tujuan_id" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Pilih Ruangan Tujuan...</option>
                            @foreach($ruanganOptions as $r)
                                @if($r->id !== $aset->ruangan_id)
                                    <option value="{{ $r->id }}">{{ $r->nama_ruangan }} ({{ $r->gedung->nama_gedung ?? 'Gedung' }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jumlah Dipindah <span class="text-rose-500">*</span></label>
                            <input type="number" name="qty_pindah" value="{{ $aset->tipe_pencatatan->value === 'unit' ? 1 : $aset->qty }}" min="1" max="{{ $aset->qty }}" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Mutasi <span class="text-rose-500">*</span></label>
                            <input type="date" name="tanggal_mutasi" value="{{ date('Y-m-d') }}" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Alasan Pemindahan <span class="text-rose-500">*</span></label>
                        <textarea name="alasan_mutasi" rows="3" required placeholder="Contoh: Kebutuhan ujian komputer kelas 9..." class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                        <button type="button" @click="openMutasi = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                        <x-primary-button type="submit">
                            Proses Mutasi
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
