<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6" x-data="{
        showTitikModal: false,
        editingTitik: null,
        formTitik: { nama: '' },
        openTitikModal(titik = null) {
            this.editingTitik = titik;
            this.formTitik = { nama: titik ? titik.nama : '' };
            this.showTitikModal = true;
        }
    }">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div>
            <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">SDM &amp; Kepegawaian</p>
            <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Konfigurasi Kehadiran SDM</h1>
            <p class="mt-1 text-sm text-gray-500">Atur metode absensi yang aktif dan titik absen untuk lembaga ini.</p>
        </div>

        {{-- Metode Absensi --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <h2 class="font-display text-sm font-bold text-gray-900">Metode Absensi Aktif</h2>
            <p class="mt-1 text-xs text-gray-500">Input manual admin selalu tersedia sebagai fallback. Metode lain bisa diaktifkan/nonaktifkan per lembaga.</p>

            <div class="mt-4 space-y-3">
                @foreach ($methods as $method)
                    @php
                        $existing = $konfigurasi->firstWhere('method', $method);
                        $isEnabled = $existing?->is_enabled ?? ($method->value === 'admin');
                    @endphp
                    <form method="POST" action="{{ route('admin.kehadiran-sdm.konfigurasi.metode') }}" class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50/60 px-4 py-3">
                        @csrf
                        <input type="hidden" name="method" value="{{ $method->value }}">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $method->label() }}</p>
                            @if ($method->value === 'admin')
                                <p class="text-[11px] text-gray-400">Fallback wajib — tidak dapat dinonaktifkan.</p>
                            @endif
                        </div>
                        @if ($method->value === 'admin')
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800">Selalu Aktif</span>
                        @else
                            <button type="submit" name="is_enabled" value="{{ $isEnabled ? '0' : '1' }}" class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $isEnabled ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600' }}">
                                {{ $isEnabled ? 'Aktif' : 'Nonaktif' }}
                            </button>
                        @endif
                    </form>
                @endforeach
            </div>
        </div>

        {{-- Titik Absen --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-sm font-bold text-gray-900">Titik Absen</h2>
                @can('kehadiran-sdm.kelola-konfigurasi')
                    <x-primary-button type="button" @click="openTitikModal()">+ Tambah Titik Absen</x-primary-button>
                @endcan
            </div>

            <div class="mt-4 divide-y divide-gray-100">
                @forelse ($titikAbsen as $titik)
                    <div class="flex items-center justify-between py-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $titik->nama }}</p>
                            <span class="text-[11px] {{ $titik->is_active ? 'text-emerald-600' : 'text-gray-400' }}">{{ $titik->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <button type="button" @click="openTitikModal({{ $titik->toJson() }})" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</button>
                                <form method="POST" action="{{ route('admin.kehadiran-sdm.titik.destroy', $titik) }}" onsubmit="return confirm('Hapus titik absen ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">Belum ada titik absen untuk lembaga ini.</p>
                @endforelse
            </div>
        </div>

        {{-- Modal Titik Absen --}}
        <div x-show="showTitikModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
            <div class="fixed inset-0 bg-gray-900/60" @click="showTitikModal = false"></div>
            <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-6 shadow-elevated">
                <h3 class="font-display text-base font-bold text-gray-900" x-text="editingTitik ? 'Edit Titik Absen' : 'Tambah Titik Absen'"></h3>
                <form method="POST" :action="editingTitik ? `/admin/kehadiran-sdm/konfigurasi/titik/${editingTitik.id}` : '{{ route('admin.kehadiran-sdm.titik.store') }}'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingTitik"><input type="hidden" name="_method" value="PUT"></template>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Titik Absen</label>
                        <input x-model="formTitik.nama" name="nama" type="text" required placeholder="Contoh: Gerbang Utama" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <template x-if="editingTitik">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" value="1" x-bind:checked="true" class="rounded border-gray-300">
                            <label class="text-xs font-semibold text-gray-700">Aktif</label>
                        </div>
                    </template>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-secondary-button type="button" @click="showTitikModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
