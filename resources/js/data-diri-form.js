export function dataDiriForm(config) {
    return {
        cekNikUrl: config.cekNikUrl,
        checking: false,
        pesanBlokir: null,
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
        keluarga: (config.old.keluarga && config.old.keluarga.length) ? config.old.keluarga : [
            { jenis: 'ayah', nama: '', pekerjaan: '' },
            { jenis: 'ibu', nama: '', pekerjaan: '' },
        ],

        tambahWali() {
            this.keluarga.push({ jenis: 'wali', nama: '', pekerjaan: '' });
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
                        this.keluarga = json.keluarga;
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
