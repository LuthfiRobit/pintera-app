<div x-show="activeTab === 'orang-tua'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    @include('admin.siswa._orang_tua', ['siswa' => $siswa])
</div>
