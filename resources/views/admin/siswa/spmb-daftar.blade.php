<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Daftarkan Siswa dari SPMB</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.siswa.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Siswa</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Daftarkan dari SPMB</b>
            </p>
        </div>

        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <form method="POST" action="{{ route('admin.siswa.spmb-daftar.store') }}" class="p-5">
                @csrf

                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <x-input-label value="Kelas Tujuan" />
                    <select name="kelas_id" class="w-full max-w-xs rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                        <option value="">— Pilih Kelas —</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}">{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-3"></th>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Jalur / Gelombang</th>
                                <th class="px-4 py-3">NIS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($pendaftaranList as $pendaftaran)
                                <tr class="transition hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" name="pendaftaran_ids[]" value="{{ $pendaftaran->id }}" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-900">{{ $pendaftaran->calonMurid->nama_lengkap }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $pendaftaran->jalurPpdb->nama }} / {{ $pendaftaran->gelombangPpdb->nama }}</td>
                                    <td class="px-4 py-3">
                                        <input type="text" name="nis[{{ $pendaftaran->id }}]" value="{{ $nisSaran }}" class="w-32 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-gray-500">Tidak ada pendaftaran yang siap didaftarkan sebagai siswa.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($pendaftaranList->isNotEmpty())
                    <x-primary-button type="submit" class="mt-4">Daftarkan sebagai Siswa</x-primary-button>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
