<x-app-layout>
    <div x-data="{
        showModalPola: false,
        modalPolaMode: 'create',
        formPola: { id: null, nama: '', actionUrl: '{{ route('admin.pola-jam.store') }}' },

        showModalEditSlot: false,
        formSlot: { id: null, hari: 'senin', urutan: 1, jam_mulai: '', jam_selesai: '', label: '', is_pelajaran: 1, updateUrl: '' },

        showModalAssign: false,
        formAssign: { polaId: null, polaNama: '', lembagaId: null, selectedKelasIds: [], actionUrl: '' },
        pencarianKelas: '',
        submitting: false,

        async muatUlangDaftar() {
            try {
                window.dispatchEvent(new CustomEvent('ajax-start'));
                const response = await fetch(window.location.href, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (response.ok) {
                    const html = await response.text();
                    const container = this.$refs.cardsContainer;
                    if (container) {
                        container.innerHTML = html;
                        if (window.Alpine) {
                            window.Alpine.initTree(container);
                        }
                    }
                } else {
                    window.Alpine?.store('toast')?.push('error', 'Gagal memuat ulang daftar pola jam.');
                }
            } catch (e) {
                window.Alpine?.store('toast')?.push('error', 'Terjadi kesalahan jaringan.');
            } finally {
                window.dispatchEvent(new CustomEvent('ajax-end'));
            }
        },

        async submitAjaxForm(formEl, callback = null) {
            if (this.submitting) return;
            this.submitting = true;
            window.dispatchEvent(new CustomEvent('ajax-start'));

            try {
                const formData = new FormData(formEl);
                const actionUrl = formEl.action || formEl.getAttribute('action');

                const response = await fetch(actionUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const result = await response.json();

                if (response.ok) {
                    const msg = result.message || 'Operasi berhasil disimpan.';
                    window.Alpine?.store('toast')?.push('success', msg);
                    if (typeof callback === 'function') {
                        callback(result);
                    }
                    await this.muatUlangDaftar();
                } else {
                    let errMsg = result.message || 'Gagal memproses permohonan.';
                    if (result.errors) {
                        const firstErr = Object.values(result.errors)[0];
                        if (Array.isArray(firstErr) && firstErr.length > 0) {
                            errMsg = firstErr[0];
                        }
                    }
                    window.Alpine?.store('toast')?.push('error', errMsg);
                }
            } catch (err) {
                window.Alpine?.store('toast')?.push('error', 'Terjadi kesalahan jaringan atau server.');
            } finally {
                this.submitting = false;
                window.dispatchEvent(new CustomEvent('ajax-end'));
            }
        },

        openCreatePola() {
            this.modalPolaMode = 'create';
            this.formPola = { id: null, nama: '', actionUrl: '{{ route('admin.pola-jam.store') }}' };
            this.showModalPola = true;
        },

        openEditPola(pola, url) {
            this.modalPolaMode = 'edit';
            this.formPola = { id: pola.id, nama: pola.nama, actionUrl: url };
            this.showModalPola = true;
        },

        openEditSlot(slot, hariValue, url) {
            this.formSlot = {
                id: slot.id,
                hari: hariValue,
                urutan: slot.urutan,
                jam_mulai: slot.jam_mulai ? String(slot.jam_mulai).substring(0, 5) : '',
                jam_selesai: slot.jam_selesai ? String(slot.jam_selesai).substring(0, 5) : '',
                label: slot.label,
                is_pelajaran: slot.is_pelajaran ? 1 : 0,
                updateUrl: url
            };
            this.showModalEditSlot = true;
        },

        openAssignModal(pola, kelasIds, url) {
            this.formAssign = {
                polaId: pola.id,
                polaNama: pola.nama,
                lembagaId: pola.lembaga_id || null,
                selectedKelasIds: Array.from(kelasIds || []).map(Number),
                actionUrl: url
            };
            this.pencarianKelas = '';
            this.showModalAssign = true;
        }
    }" class="mx-auto max-w-6xl space-y-6">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-lg font-bold text-gray-900">Pola Jam &amp; Jam Pelajaran</h1>
                    @if ($isYayasan ?? false)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                            <x-icon name="apartment" class="h-3.5 w-3.5" />
                            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mt-0.5">Kelola jadwal waktu belajar harian dan tautkan dengan kelas yang relevan.</p>
            </div>
            <div class="flex items-center gap-4">
                @can('pola-jam.create')
                    @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                        <x-tooltip text="Pilih lembaga aktif lewat pengalih lembaga terlebih dahulu">
                            <x-primary-button type="button" disabled class="shrink-0 justify-center opacity-50 cursor-not-allowed">
                                <span class="text-base leading-none mr-1.5">+</span> Tambah Pola Jam
                            </x-primary-button>
                        </x-tooltip>
                    @else
                        <x-primary-button type="button" @click="openCreatePola()" class="shrink-0 justify-center">
                            <span class="text-base leading-none mr-1.5">+</span> Tambah Pola Jam
                        </x-primary-button>
                    @endif
                @endcan
                <p class="hidden sm:block text-sm text-gray-500">
                    Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pola Jam</b>
                </p>
            </div>
        </div>

        {{-- Daftar Card Pola Jam & KPI --}}
        <div x-ref="cardsContainer" id="pola-jam-cards-container" class="space-y-6">
            @include('portals.lembaga.akademik.pola-jam._daftar')
        </div>

        {{-- Include SPA Modal Partials --}}
        @include('portals.lembaga.akademik.pola-jam._modal-pola')
        @include('portals.lembaga.akademik.pola-jam._modal-edit-slot')
        @include('portals.lembaga.akademik.pola-jam._modal-assign-kelas')
    </div>
</x-app-layout>