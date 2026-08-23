{{-- resources/views/sdm/izin-cuti/create.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-0">
        
        {{-- Flash Notification --}}
        @if (session('status'))
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-xs font-semibold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Inline Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Ajukan Izin / Cuti Mandiri</h1>
                <p class="text-xs text-gray-500 mt-0.5">Permohonan izin atau cuti pegawai untuk diproses oleh pihak penanggung jawab.</p>
            </div>
            <div class="flex items-center gap-2">
                <p class="hidden text-sm text-gray-500 sm:block mr-2">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Kehadiran <span class="mx-1 text-gray-300">&rsaquo;</span> Izin &amp; Cuti <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Buat Pengajuan</b>
                </p>
                <a href="{{ route('sdm.izin-cuti.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50">
                    &larr; <span>Riwayat Izin/Cuti</span>
                </a>
            </div>
        </div>

        {{-- Main Form Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            <form 
                method="POST" 
                action="{{ route('sdm.izin-cuti.store') }}" 
                class="space-y-5"
                x-data="{ kategori: '{{ old('kategori') }}' }"
                @submit.prevent="confirmDialog(
                    'Ajukan Permohonan Izin/Cuti?', 
                    'Apakah Anda yakin data permohonan izin/cuti ini sudah benar dan siap dikirim?', 
                    { confirmLabel: 'Ya, Kirim Pengajuan' }
                ).then(confirmed => { if (confirmed) $el.submit() })"
            >
                @csrf

                {{-- Kategori Dropdown --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kategori Permohonan <span class="text-rose-500">*</span></label>
                    <select name="kategori" x-model="kategori" required class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Kategori Permohonan —</option>
                        @foreach ($kategoriOptions as $kategori)
                            <option value="{{ $kategori->value }}" {{ old('kategori') === $kategori->value ? 'selected' : '' }}>
                                {{ $kategori->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($adaKonfigurasiKuota)
                    <div x-show="kategori === 'cuti'" x-cloak class="rounded-xl border border-brand-100 bg-brand-50/50 p-3.5 text-xs font-semibold text-brand-800">
                        Sisa kuota Cuti Anda tahun ini: <span class="font-mono">{{ $sisaKuotaCuti }}</span> hari.
                    </div>
                @endif

                {{-- Tanggal Range Grid --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Mulai <span class="text-rose-500">*</span></label>
                        <input 
                            type="date" 
                            name="tanggal_mulai" 
                            required 
                            value="{{ old('tanggal_mulai', date('Y-m-d')) }}" 
                            class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 font-mono text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Selesai <span class="text-rose-500">*</span></label>
                        <input 
                            type="date" 
                            name="tanggal_selesai" 
                            required 
                            value="{{ old('tanggal_selesai', date('Y-m-d')) }}" 
                            class="w-full rounded-xl border-gray-200 bg-gray-50 p-2.5 font-mono text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"
                        >
                    </div>
                </div>

                {{-- Alasan Penjelasan --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Alasan Permohonan <span class="text-rose-500">*</span></label>
                    <textarea 
                        name="alasan" 
                        rows="4" 
                        required 
                        placeholder="Jelaskan keperluan atau alasan permohonan izin/cuti Anda secara rinci..." 
                        class="w-full rounded-xl border-gray-200 bg-gray-50 p-3 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"
                    >{{ old('alasan') }}</textarea>
                </div>

                {{-- Form Actions Footer --}}
                <div class="flex items-center justify-end gap-2.5 border-t border-gray-100 pt-4">
                    <a href="{{ route('sdm.izin-cuti.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50 active:scale-[0.98]">
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 active:scale-[0.98]">
                        <span>Kirim Pengajuan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
