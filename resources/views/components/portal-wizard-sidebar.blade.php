{{-- resources/views/components/portal-wizard-sidebar.blade.php --}}
@props(['lembaga', 'jalur', 'nominal' => null])

<aside>
    <div class="rounded-2xl border border-gray-200 bg-white p-5">
        <p class="mb-4 text-[11px] font-bold uppercase tracking-wide text-gray-400">Pilihan Jalur</p>
        <div class="mb-4 flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-portal-50 text-portal-500">
                <x-icon name="school" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="truncate text-[14px] font-bold text-gray-900">Jalur {{ $jalur->nama }}</p>
                <p class="truncate text-[11px] text-gray-400">{{ $lembaga->nama }}</p>
            </div>
        </div>
        <div class="flex items-center justify-between border-t border-dashed border-gray-200 py-2.5 text-[12.5px]">
            <span class="text-gray-400">Biaya Pendaftaran</span>
            @if ($nominal === null)
                <span class="font-bold text-warning-700">Menunggu Konfirmasi</span>
            @elseif ((float) $nominal->nominal === 0.0)
                <span class="font-bold text-success-700">Gratis</span>
            @else
                <span class="font-bold text-gray-900">Rp{{ number_format($nominal->nominal, 0, ',', '.') }}</span>
            @endif
        </div>
    </div>

    <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5">
        <p class="mb-3 text-[11px] font-bold uppercase tracking-wide text-gray-400">Butuh Bantuan?</p>
        <p class="text-[12px] leading-relaxed text-gray-500">Data yang sudah kamu simpan otomatis tersimpan sebagai draf — kamu bisa lanjutkan kapan saja sebelum gelombang ditutup.</p>
        <a href="{{ route('portal.dashboard') }}" class="mt-3 inline-flex items-center gap-1 text-[12.5px] font-bold text-portal-500">
            Kembali ke Dashboard
            <x-icon name="arrow_forward" class="h-3 w-3" />
        </a>
    </div>
</aside>
