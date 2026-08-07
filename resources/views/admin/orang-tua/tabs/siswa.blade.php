<div x-show="activeTab === 'siswa'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-gradient-to-r from-white to-gray-50/80 p-6 shadow-card backdrop-blur">
            <div>
                <h2 class="font-display text-lg font-bold text-gray-900">Anak / Siswa Tertaut</h2>
                <p class="text-sm text-gray-500">Daftar siswa yang terhubung dengan orang tua ini. Penautan dilakukan dari Profil Siswa.</p>
            </div>
        </div>

        {{-- Data Presentation --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            @if ($orangTua->siswa->isEmpty())
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 text-gray-400">
                        <x-icon name="groups" class="h-8 w-8" />
                    </div>
                    <h3 class="mt-4 font-semibold text-gray-900">Belum Ada Anak Tertaut</h3>
                    <p class="mt-1 max-w-sm text-sm text-gray-500">Orang tua ini belum dihubungkan dengan profil siswa mana pun. Penautan bisa dilakukan dari halaman Edit Siswa.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-4">Nama Siswa</th>
                                <th class="px-6 py-4">NISN</th>
                                <th class="px-6 py-4">Hubungan</th>
                                <th class="px-6 py-4">Status Kontak</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-normal">
                            @foreach ($orangTua->siswa as $siswa)
                                <tr class="transition hover:bg-gray-50/50">
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ $siswa->nama_lengkap }}</td>
                                    <td class="px-6 py-4 font-mono">{{ $siswa->nisn ?: '-' }}</td>
                                    <td class="px-6 py-4">{{ ucfirst($siswa->pivot->hubungan) }}</td>
                                    <td class="px-6 py-4">
                                        @if ($siswa->pivot->is_kontak_utama)
                                            <span class="inline-flex items-center gap-1 rounded bg-green-50 px-2 py-1 text-xs font-semibold text-green-700">
                                                <x-icon name="check_circle" class="h-3 w-3" />
                                                Kontak Utama
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
