import TomSelect from 'tom-select';

export function jenisTagihanForm(config) {
    let uidCounter = 0;
    const nextUid = () => ++uidCounter;
    const hydrateGrup = (grup) => ({ 
        uid: nextUid(), 
        nominal: grup.nominal ?? '', 
        kriteria: grup.kriteria.map((k) => ({ uid: nextUid(), ...k })) 
    });

    return {
        kriteriaFields: ['lembaga', 'tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa'],
        fieldLabels: {
            lembaga: 'Lembaga', tahun_ajaran: 'Tahun Ajaran', tingkat: 'Tingkat',
            kelas: 'Kelas', jenis_kelamin: 'Jenis Kelamin', status_siswa: 'Status Siswa',
        },
        referenceOptions: config.referenceOptions,
        sasaranMode: config.initialSasaran.length > 0 ? 'kriteria' : 'semua',
        form: {
            kategori: config.kategoriAwal,
            mode: config.modeAwal,
            bisaDicicil: config.bisaDicicilAwal,
            sasaran: config.initialSasaran.map(hydrateGrup),
            tarif: config.initialTarif.map(hydrateGrup),
            keringanan: config.initialKeringanan.map((k) => ({ uid: nextUid(), ...k })),
        },
        kategoriKeringananOptions: config.kategoriKeringananList,
        kategoriBaruNama: '',
        kategoriBaruError: '',
        kategoriBaruSubmitting: false,
        showKategoriBaru: false,
        tomSelectInstances: {},

        get kategoriPpdb() {
            return ['pendaftaran', 'daftar_ulang'].includes(this.form.kategori);
        },

        hydrateGrup,

        newKeringanan() {
            return { uid: nextUid(), kategori_keringanan_id: null, tipe_potongan: 'fixed', nilai: '', keterangan: '' };
        },

        async submitKategoriBaru() {
            if (!this.kategoriBaruNama.trim()) {
                this.kategoriBaruError = 'Nama kategori tidak boleh kosong.';
                return;
            }

            this.kategoriBaruSubmitting = true;
            this.kategoriBaruError = '';
            
            try {
                const response = await fetch(config.kategoriKeringananStoreUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ nama: this.kategoriBaruNama }),
                });
                
                const json = await response.json();
                
                if (!response.ok) {
                    this.kategoriBaruError = json.message ?? 'Gagal menambah kategori.';
                    return;
                }
                
                this.kategoriKeringananOptions.push(json.data);
                this.kategoriBaruNama = '';
                this.showKategoriBaru = false;
                Alpine.store('toast').push('success', 'Kategori Keringanan berhasil ditambahkan.');
            } catch (error) {
                this.kategoriBaruError = 'Gagal menambah kategori.';
            } finally {
                this.kategoriBaruSubmitting = false;
            }
        },

        newKriteria() {
            return { uid: nextUid(), field: 'status_siswa', operator: 'in', value: [] };
        },

        newGrup() {
            return { uid: nextUid(), nominal: '', kriteria: [this.newKriteria()] };
        },

        optionsFor(field) {
            if (field === 'jenis_kelamin') return [{ value: 'L', label: 'Laki-laki' }, { value: 'P', label: 'Perempuan' }];
            if (field === 'status_siswa') return [{ value: 'aktif', label: 'Aktif' }, { value: 'lulus', label: 'Lulus' }, { value: 'pindah', label: 'Pindah' }, { value: 'keluar', label: 'Keluar' }];
            return this.referenceOptions[field] ?? [];
        },

        initTomSelect(el, kriteria) {
            if (el.tomselect) {
                el.tomselect.clear();
                el.tomselect.clearOptions();
                el.tomselect.sync();
                return;
            }
            
            const ts = new TomSelect(el, {
                plugins: ['remove_button'],
                create: false,
                placeholder: 'Pilih (bisa pilih lebih dari satu)...',
                onChange: (value) => {
                    kriteria.value = Array.isArray(value) ? value : (value ? [value] : []);
                },
            });
            
            if (kriteria.value && kriteria.value.length > 0) {
                ts.setValue(kriteria.value, true);
            }
            
            this.tomSelectInstances[kriteria.uid] = ts;
        },

        validateBeforeSubmit(event) {
            const formEl = event.target;
            const namaInput = formEl.querySelector('input[name="nama"]');
            
            if (namaInput && !namaInput.value.trim()) {
                Alpine.store('toast').push('error', 'Nama Jenis Tagihan harus diisi!');
                namaInput.focus();
                return;
            }

            if (this.sasaranMode === 'kriteria') {
                if (this.form.sasaran.length === 0) {
                    Alpine.store('toast').push('error', 'Minimal ada 1 Grup Sasaran jika menggunakan kriteria.');
                    return;
                }
                for (let i = 0; i < this.form.sasaran.length; i++) {
                    const grup = this.form.sasaran[i];
                    if (grup.kriteria.length === 0) {
                        Alpine.store('toast').push('error', `Grup Sasaran ke-${i+1} tidak memiliki kriteria sama sekali.`);
                        return;
                    }
                    for (const kr of grup.kriteria) {
                        if (!kr.value || kr.value.length === 0) {
                            Alpine.store('toast').push('error', `Ada kriteria di Grup Sasaran ke-${i+1} yang nilainya masih kosong.`);
                            return;
                        }
                    }
                }
            }

            if (this.form.tarif.length > 0) {
                for (let i = 0; i < this.form.tarif.length; i++) {
                    const grup = this.form.tarif[i];
                    if (!grup.nominal || parseFloat(grup.nominal) <= 0) {
                        Alpine.store('toast').push('error', `Nominal pada Grup Tarif ke-${i+1} tidak valid.`);
                        return;
                    }
                }
            }

            formEl.submit();
        }
    };
}
