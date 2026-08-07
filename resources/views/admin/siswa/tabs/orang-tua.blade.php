<div x-show="activeTab === 'orang-tua'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="space-y-6">
        <div class="flex items-center justify-between rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Orang Tua / Wali Tertaut</h2>
                <p class="text-sm text-gray-500">Daftar orang tua atau wali yang bertanggung jawab dan memiliki akses ke profil siswa ini.</p>
            </div>
        </div>

        @include('admin.siswa._orang_tua', ['siswa' => $siswa])
    </div>
</div>
