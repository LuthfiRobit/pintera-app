<div x-show="activeTab === 'kartu-digital'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
    <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card space-y-5">
        <div>
            <h3 class="font-semibold text-gray-900">Kartu Digital Siswa</h3>
            <p class="text-xs text-gray-400">Kode QR untuk presensi via scan di Jurnal KBM.</p>
        </div>

        @php $kartu = $siswa->kartuSiswa()->where('tipe', 'qr')->first(); @endphp

        @if ($kartu)
            <dl class="divide-y divide-gray-100 text-sm">
                <div class="flex justify-between py-2.5">
                    <dt class="text-gray-500">Status</dt>
                    <dd>
                        <x-badge tone="{{ $kartu->is_active ? 'green' : 'amber' }}">{{ $kartu->is_active ? 'Aktif' : 'Non-aktif' }}</x-badge>
                    </dd>
                </div>
                <div class="flex justify-between py-2.5">
                    <dt class="text-gray-500">Kode</dt>
                    <dd class="font-mono text-xs text-gray-900">{{ substr($kartu->kode, 0, 8) }}...</dd>
                </div>
                <div class="flex justify-between py-2.5">
                    <dt class="text-gray-500">Dibuat</dt>
                    <dd class="text-gray-900">{{ $kartu->created_at->format('d F Y') }}</dd>
                </div>
            </dl>

            <div class="flex gap-3 pt-2 border-t border-gray-100">
                @can('siswa.edit')
                    <form method="POST" action="{{ route('admin.siswa.kartu-digital.generate-ulang', $siswa) }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Generate Ulang
                        </button>
                    </form>
                    @if ($kartu->is_active)
                        <form method="POST" action="{{ route('admin.siswa.kartu-digital.nonaktifkan', $siswa) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-rose-200 bg-white px-3.5 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                Nonaktifkan
                            </button>
                        </form>
                    @endif
                @endcan
            </div>
        @else
            <p class="text-sm text-gray-500">Siswa belum pernah membuka halaman Kartu Digital Saya, jadi kartu belum ada.</p>
        @endif
    </div>
</div>
