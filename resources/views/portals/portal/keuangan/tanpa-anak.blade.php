{{-- resources/views/keuangan/tanpa-anak.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Dompet &amp; Tagihan</h2>
    </x-slot>

    <div class="flex flex-col items-center gap-3 rounded-2xl border border-gray-200 bg-white p-10 text-center">
        <x-icon name="family_restroom" class="h-8 w-8 text-gray-300" />
        <p class="font-display text-base font-bold text-gray-900">Belum ada anak terdaftar</p>
        <p class="max-w-md text-sm text-gray-500">Akun Anda belum terhubung dengan data siswa manapun. Silakan hubungi admin lembaga untuk menautkan profil anak Anda.</p>
    </div>
</x-app-layout>
