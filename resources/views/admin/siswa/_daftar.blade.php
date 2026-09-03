<div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-2.5">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Siswa</p>
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ $siswaList->total() }} Data</span>
    </div>

    <div class="flex items-center gap-2">
        <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
        <select id="per_page" x-model="perPage" @change="muatUlangDaftar()" class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
            <option value="10">10 / hal</option>
            <option value="20">20 / hal</option>
            <option value="25">25 / hal</option>
            <option value="50">50 / hal</option>
        </select>
    </div>
</div>

<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                <th class="px-5 py-3">NIS</th>
                <th class="px-5 py-3">Nama</th>
                <th class="px-5 py-3">Kelas</th>
                <th class="px-5 py-3">Asal Data</th>
                <th class="px-5 py-3">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($siswaList as $siswa)
                <tr class="transition hover:bg-gray-50">
                    <td class="sticky left-0 z-10 bg-white px-5 py-3">
                        <x-table-actions>
                            <x-dropdown-link :href="route('admin.siswa.edit', $siswa)">
                                <span class="inline-flex items-center gap-2.5">
                                    <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                    Edit Siswa
                                </span>
                            </x-dropdown-link>
                            @foreach (\App\Enums\StatusSiswa::cases() as $statusOption)
                                @if ($statusOption !== $siswa->status)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.siswa.update-status', $siswa) }}"
                                        x-data
                                        @submit.prevent="confirmDialog('Ubah Status Siswa?', @js('Ubah status \"' . $siswa->nama_lengkap . '\" menjadi \"' . $statusOption->label() . '\"?'), { confirmLabel: 'Ya, Ubah' }).then(confirmed => { if (confirmed) $el.submit() })"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $statusOption->value }}">
                                        <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                            <x-icon name="autorenew" class="h-4 w-4 text-gray-500" />
                                            Jadikan {{ $statusOption->label() }}
                                        </button>
                                    </form>
                                @endif
                            @endforeach
                            @if ($siswa->user_id)
                                <form
                                    method="POST"
                                    action="{{ route('admin.siswa.reset-password', $siswa) }}"
                                    x-data
                                    @submit.prevent="confirmDialog('Reset Password Siswa?', @js('Reset password \"' . $siswa->nama_lengkap . '\" kembali ke NIS (' . $siswa->nis . ')? Siswa wajib menggantinya saat login berikutnya.'), { confirmLabel: 'Ya, Reset' }).then(confirmed => { if (confirmed) $el.submit() })"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                        <x-icon name="autorenew" class="h-4 w-4 text-gray-500" />
                                        Reset Password ke NIS
                                    </button>
                                </form>
                            @else
                                <form
                                    method="POST"
                                    action="{{ route('admin.siswa.generate-akun', $siswa) }}"
                                    x-data
                                    @submit.prevent="confirmDialog('Buat Akun Login?', @js('Buat akun login untuk siswa \"' . $siswa->nama_lengkap . '\" dengan username berdasarkan NIS (' . $siswa->nis . ')?'), { confirmLabel: 'Ya, Buat Akun' }).then(confirmed => { if (confirmed) $el.submit() })"
                                >
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-brand-700 font-semibold transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                        <x-icon name="person_add" class="h-4 w-4 text-brand-600" />
                                        Buat Akun Login
                                    </button>
                                </form>
                            @endif
                        </x-table-actions>
                    </td>
                    <td class="px-5 py-3.5 font-mono text-gray-500">{{ $siswa->nis }}</td>
                    <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $siswa->nama_lengkap }}</td>
                    <td class="px-5 py-3.5 text-gray-600">
                        @if ($siswa->kelas_efektif)
                            {{ $siswa->kelas_efektif->nama }}
                            @if (! $siswa->kelas && $siswa->kelasTerakhir)
                                <span class="text-xs text-gray-400">(kelas terakhir)</span>
                            @endif
                        @else
                            <x-badge tone="amber">Belum ditempatkan</x-badge>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <x-badge tone="slate">{{ $siswa->sumber_data->label() }}</x-badge>
                    </td>
                    <td class="px-5 py-3.5">
                        <x-badge :tone="$siswa->status === \App\Enums\StatusSiswa::Aktif ? 'green' : 'slate'">{{ $siswa->status->label() }}</x-badge>
                    </td>
                </tr>
            @endforeach

            @if ($siswaList->isEmpty())
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-gray-500">
                        Belum ada siswa yang didaftarkan.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>

@if ($siswaList->hasPages())
    <div class="border-t border-gray-200 px-5 py-4">
        {{ $siswaList->links('pagination.tailadmin') }}
    </div>
@endif
