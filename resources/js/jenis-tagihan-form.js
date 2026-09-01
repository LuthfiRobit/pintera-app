import TomSelect from 'tom-select';

export function jenisTagihanForm(config) {
    let uidCounter = 0;
    const nextUid = () => ++uidCounter;

    const formatRupiah = (val) => {
        if (val === null || val === undefined || val === '') return '';
        const clean = String(val).replace(/[^0-9]/g, '');
        if (!clean) return '';
        return new Intl.NumberFormat('id-ID').format(clean);
    };

    const unformatRupiah = (str) => {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[^0-9]/g, '');
    };

    const hydrateGrup = (grup) => ({ 
        uid: nextUid(), 
        nominalDisplay: grup.nominal ? formatRupiah(grup.nominal) : '',
        nominal: grup.nominal ? String(grup.nominal) : '', 
        kriteria: (grup.kriteria || []).map((k) => ({ uid: nextUid(), ...k })) 
    });

    const hydrateKeringanan = (k) => ({
        uid: nextUid(),
        kategori_keringanan_id: k.kategori_keringanan_id ?? null,
        tipe_potongan: k.tipe_potongan ?? 'fixed',
        nilaiDisplay: k.tipe_potongan === 'fixed' && k.nilai ? formatRupiah(k.nilai) : (k.nilai ? String(k.nilai) : ''),
        nilai: k.nilai ? String(k.nilai) : '',
        keterangan: k.keterangan ?? '',
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
            tipe: config.tipeAwal ?? (config.modeAwal === 'manual' ? 'sekali' : 'bulanan'),
            defaultAmountDisplay: config.defaultAmountAwal ? formatRupiah(config.defaultAmountAwal) : '',
            defaultAmount: config.defaultAmountAwal ? String(config.defaultAmountAwal) : '',
            bisaDicicil: config.bisaDicicilAwal,
            sasaran: config.initialSasaran.map(hydrateGrup),
            tarif: config.initialTarif.map(hydrateGrup),
            keringanan: config.initialKeringanan.map(hydrateKeringanan),
        },
        kategoriKeringananOptions: config.kategoriKeringananList,
        kategoriBaruNama: '',
        kategoriBaruError: '',
        kategoriBaruSubmitting: false,
        showKategoriBaru: false,
        tomSelectInstances: {},

        formatRupiah,
        unformatRupiah,

        onDefaultAmountInput(event) {
            const raw = unformatRupiah(event.target.value);
            this.form.defaultAmount = raw;
            this.form.defaultAmountDisplay = raw ? formatRupiah(raw) : '';
        },

        onTarifNominalInput(grup, event) {
            const raw = unformatRupiah(event.target.value);
            grup.nominal = raw;
            grup.nominalDisplay = raw ? formatRupiah(raw) : '';
        },

        onKeringananNilaiInput(rule, event) {
            if (rule.tipe_potongan === 'fixed') {
                const raw = unformatRupiah(event.target.value);
                rule.nilai = raw;
                rule.nilaiDisplay = raw ? formatRupiah(raw) : '';
            } else {
                rule.nilai = event.target.value;
                rule.nilaiDisplay = event.target.value;
            }
        },

        onKeringananTipeChange(rule) {
            if (rule.tipe_potongan === 'fixed') {
                const raw = unformatRupiah(rule.nilai);
                rule.nilai = raw;
                rule.nilaiDisplay = raw ? formatRupiah(raw) : '';
            } else {
                const num = parseFloat(rule.nilai) || 0;
                rule.nilai = num > 100 ? '100' : (rule.nilai || '');
                rule.nilaiDisplay = rule.nilai;
            }
        },

        onModeChange() {
            if (this.form.mode === 'otomatis' && this.form.tipe === 'sekali') {
                this.form.tipe = 'bulanan';
            }
        },

        onTipeChange() {
            if (this.form.mode === 'otomatis' && this.form.tipe === 'sekali') {
                this.form.tipe = 'bulanan';
            }
        },

        get kategoriPpdb() {
            return ['pendaftaran', 'daftar_ulang'].includes(this.form.kategori);
        },

        hydrateGrup,

        newKeringanan() {
            return { uid: nextUid(), kategori_keringanan_id: null, tipe_potongan: 'fixed', nilaiDisplay: '', nilai: '', keterangan: '' };
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
            return { uid: nextUid(), nominalDisplay: '', nominal: '', kriteria: [this.newKriteria()] };
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
