{{-- resources/views/spmb/review.blade.php --}}
<x-layouts.portal-wizard title="Review" current="review" :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal">
    <div class="rounded-2xl border border-gray-200 bg-white p-6" x-data="{}">
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-portal-50 text-portal-500">
                <x-icon name="fact_check" class="h-4 w-4" />
            </span>
            <h2 class="text-[15.5px] font-bold text-gray-900">Review Data</h2>
        </div>
        <p class="mt-1.5 text-[12.5px] text-gray-500">Periksa kembali sebelum mengirim.</p>
        <div class="my-4 h-px bg-gray-200"></div>

        @error('submit')
            <div class="mb-4 rounded-xl border border-error-500/30 bg-error-50 p-4 text-[13px] text-error-700">{{ $message }}</div>
        @enderror

        <div class="space-y-4">
            <section class="rounded-xl border border-gray-200 p-4">
                <div class="mb-3 flex items-center gap-2">
                    <x-icon name="person" class="h-4 w-4 text-portal-500" />
                    <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Data Pribadi</h3>
                </div>
                <dl class="divide-y divide-gray-100 text-[13px]">
                    <div class="flex justify-between py-2"><dt class="text-gray-400">Nama Lengkap</dt><dd class="font-semibold text-gray-900">{{ $session['data_pribadi']['nama_lengkap'] ?? '-' }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-gray-400">NIK</dt><dd class="font-mono text-gray-900">{{ $session['nik'] ?? '-' }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-gray-400">Jenis Kelamin</dt><dd class="text-gray-900">{{ $session['data_pribadi']['jenis_kelamin'] ?? '-' }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-gray-400">Tempat, Tanggal Lahir</dt><dd class="text-gray-900">{{ $session['data_pribadi']['tempat_lahir'] ?? '-' }}, {{ $session['data_pribadi']['tanggal_lahir'] ?? '-' }}</dd></div>
                    <div class="flex justify-between py-2"><dt class="text-gray-400">Agama</dt><dd class="text-gray-900">{{ $session['data_pribadi']['agama'] ?? '-' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-xl border border-gray-200 p-4">
                <div class="mb-3 flex items-center gap-2">
                    <x-icon name="location_on" class="h-4 w-4 text-portal-500" />
                    <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Alamat</h3>
                </div>
                <p class="text-[13px] text-gray-900">
                    {{ $session['alamat']['alamat_jalan'] ?? '-' }}, {{ $session['alamat']['desa_kelurahan'] ?? '-' }},
                    {{ $session['alamat']['kecamatan'] ?? '-' }}, {{ $session['alamat']['kabupaten_kota'] ?? '-' }}, {{ $session['alamat']['provinsi'] ?? '-' }}
                </p>
            </section>

            <section class="rounded-xl border border-gray-200 p-4">
                <div class="mb-3 flex items-center gap-2">
                    <x-icon name="group" class="h-4 w-4 text-portal-500" />
                    <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Data Orang Tua/Wali</h3>
                </div>
                <dl class="divide-y divide-gray-100 text-[13px]">
                    @foreach ($session['keluarga'] ?? [] as $anggota)
                        <div class="flex justify-between py-2"><dt class="text-gray-400">{{ ucfirst($anggota['jenis']) }}</dt><dd class="font-semibold text-gray-900">{{ $anggota['nama'] }}</dd></div>
                    @endforeach
                </dl>
            </section>

            @if ($formulirFieldList->isNotEmpty())
                <section class="rounded-xl border border-gray-200 p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <x-icon name="quiz" class="h-4 w-4 text-portal-500" />
                        <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Formulir Tambahan</h3>
                    </div>
                    <dl class="divide-y divide-gray-100 text-[13px]">
                        @foreach ($formulirFieldList as $field)
                            <div class="flex items-center justify-between gap-3 py-2">
                                <dt class="text-gray-400">{{ $field->label }}</dt>
                                @if ($field->field_type === 'file' && isset($session['jawaban_formulir'][$field->id]))
                                    @php
                                        $jawabanFile = $session['jawaban_formulir'][$field->id];
                                        // Deliberately a root-relative path, not Storage::url() — that helper
                                        // prefixes APP_URL, which can point at a different origin than the
                                        // vhost actually being browsed (e.g. Laragon's *.test domains), and
                                        // this file is always fetched client-side from the same page.
                                        $urlFile = '/storage/'.$jawabanFile['file_path'];
                                    @endphp
                                    <dd class="flex shrink-0 items-center gap-2.5">
                                        <span class="truncate font-semibold text-gray-900">{{ $jawabanFile['nama_file_asli'] }}</span>
                                        <button type="button" class="shrink-0 text-[11.5px] font-bold text-portal-500"
                                            @click="$store.filePreview.buka('{{ $urlFile }}', '{{ $jawabanFile['mime_type'] }}', '{{ $jawabanFile['nama_file_asli'] }}')">
                                            Lihat
                                        </button>
                                    </dd>
                                @elseif ($field->field_type === 'file')
                                    <dd class="font-semibold text-gray-400">Belum diupload</dd>
                                @else
                                    <dd class="font-semibold text-gray-900">{{ $session['jawaban_formulir'][$field->id] ?? '-' }}</dd>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            @if ($dokumenSyaratList->isNotEmpty())
                <section class="rounded-xl border border-gray-200 p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <x-icon name="receipt_long" class="h-4 w-4 text-portal-500" />
                        <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Dokumen</h3>
                    </div>
                    <dl class="divide-y divide-gray-100 text-[13px]">
                        @foreach ($dokumenSyaratList as $syarat)
                            <div class="flex items-center justify-between gap-3 py-2">
                                <dt class="text-gray-400">{{ $syarat->nama_dokumen }}</dt>
                                @if (isset($session['dokumen'][$syarat->id]))
                                    @php
                                        $dokumenTerupload = $session['dokumen'][$syarat->id];
                                        // See the same note above — root-relative on purpose, not Storage::url().
                                        $urlDokumen = '/storage/'.$dokumenTerupload['file_path'];
                                    @endphp
                                    <dd class="flex shrink-0 items-center gap-2.5">
                                        <span class="truncate font-semibold text-success-700">{{ $dokumenTerupload['nama_file_asli'] }}</span>
                                        <button type="button" class="shrink-0 text-[11.5px] font-bold text-portal-500"
                                            @click="$store.filePreview.buka('{{ $urlDokumen }}', '{{ $dokumenTerupload['mime_type'] }}', '{{ $dokumenTerupload['nama_file_asli'] }}')">
                                            Lihat
                                        </button>
                                    </dd>
                                @else
                                    <dd class="font-semibold text-gray-400">Belum diupload</dd>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>

        <form method="POST" action="{{ route('portal.wizard.submit') }}" class="mt-5 flex justify-end border-t border-dashed border-gray-200 pt-5">
            @csrf
            <button type="submit" class="flex items-center justify-center gap-2 rounded-[10px] bg-portal-500 px-6 py-3 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                Kirim Pendaftaran
                <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
            </button>
        </form>
    </div>

    <x-portal-file-preview-modal />
</x-layouts.portal-wizard>
