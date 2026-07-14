<x-spmb-public-layout :lembaga="$lembaga" title="Pendaftaran Ditutup">
    <x-panel class="p-6 text-center">
        <p class="font-display text-lg font-bold text-spmb-primary">Pendaftaran belum dibuka</p>
        <p class="mt-2 text-sm text-slate">Saat ini tidak ada gelombang pendaftaran yang sedang berlangsung untuk {{ $lembaga->nama }}. Silakan cek kembali nanti.</p>
    </x-panel>
</x-spmb-public-layout>
