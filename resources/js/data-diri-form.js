import flatpickr from 'flatpickr';
import TomSelect from 'tom-select';

export function dataDiriForm(config) {
    return {
        cekNikUrl: config.cekNikUrl,
        checking: false,
        pesanBlokir: null,
        tanggalLahirPicker: null,
        pekerjaanSelects: {},
        form: {
            nama_lengkap: config.old.nama_lengkap ?? '',
            nisn: config.old.nisn ?? '',
            jenis_kelamin: config.old.jenis_kelamin ?? '',
            tempat_lahir: config.old.tempat_lahir ?? '',
            tanggal_lahir: config.old.tanggal_lahir ?? '',
            agama: config.old.agama ?? '',
            golongan_darah: config.old.golongan_darah ?? '',
            no_telepon: config.old.no_telepon ?? '',
            alamat_jalan: config.old.alamat_jalan ?? '',
            rt: config.old.rt ?? '',
            rw: config.old.rw ?? '',
            dusun: config.old.dusun ?? '',
            desa_kelurahan: config.old.desa_kelurahan ?? '',
            kecamatan: config.old.kecamatan ?? '',
            kabupaten_kota: config.old.kabupaten_kota ?? '',
            provinsi: config.old.provinsi ?? '',
            kode_pos: config.old.kode_pos ?? '',
        },
        keluarga: {
            ayah: { nama: config.old.nama_ayah ?? '', pekerjaan: config.old.pekerjaan_ayah ?? '' },
            ibu: { nama: config.old.nama_ibu ?? '', pekerjaan: config.old.pekerjaan_ibu ?? '' },
            wali: { nama: config.old.nama_wali ?? '', pekerjaan: config.old.pekerjaan_wali ?? '' },
        },

        initTanggalLahir(el) {
            this.tanggalLahirPicker = flatpickr(el, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j F Y',
                altInputClass: el.className,
                maxDate: 'today',
                onChange: (selectedDates, dateStr) => {
                    this.form.tanggal_lahir = dateStr;
                },
            });
            if (this.form.tanggal_lahir) {
                this.tanggalLahirPicker.setDate(this.form.tanggal_lahir, true);
            }
        },

        initPekerjaanSelect(el, jenis) {
            this.pekerjaanSelects[jenis] = new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari pekerjaan...',
                onChange: (value) => {
                    this.keluarga[jenis].pekerjaan = value;
                },
            });
        },

        tambahkanKeAnggotaKeluarga(jenis, anggota) {
            if (!this.keluarga[jenis]) {
                return;
            }
            this.keluarga[jenis].nama = anggota.nama ?? '';
            this.keluarga[jenis].pekerjaan = anggota.pekerjaan ?? '';
            // setValue() is a no-op if the incoming value isn't one of the select's
            // options — e.g. a pekerjaan recorded before this curated list existed.
            this.pekerjaanSelects[jenis]?.setValue(anggota.pekerjaan ?? '', true);
        },

        async cekNik(nik) {
            this.pesanBlokir = null;
            if (!/^\d{16}$/.test(nik)) {
                return;
            }
            this.checking = true;
            try {
                const response = await fetch(this.cekNikUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ nik }),
                });
                const json = await response.json();

                if (response.status === 422 && json.diblokir) {
                    this.pesanBlokir = json.pesan;
                    return;
                }

                if (json.ditemukan) {
                    Object.assign(this.form, json.data_pribadi ?? {});
                    if (json.alamat) {
                        Object.assign(this.form, json.alamat);
                    }
                    if (json.keluarga && json.keluarga.length) {
                        for (const anggota of json.keluarga) {
                            this.tambahkanKeAnggotaKeluarga(anggota.jenis, anggota);
                        }
                    }
                    if (this.form.tanggal_lahir && this.tanggalLahirPicker) {
                        this.tanggalLahirPicker.setDate(this.form.tanggal_lahir, true);
                    }
                }
            } catch (error) {
                // Network/parse failure: silently ignore. This is a UX convenience —
                // the server re-validates the same safeguard on actual submit regardless.
            } finally {
                this.checking = false;
            }
        },
    };
}
