# Bab 0 — Setup Lembaga

## Untuk siapa

Admin Yayasan (`yayasan_super_admin`). Ini adalah langkah paling awal sebelum modul
akademik bisa dipakai sama sekali — semua bab berikutnya butuh minimal satu Lembaga aktif.

## Prasyarat

- Akun dengan role `yayasan_super_admin` sudah ada (dibuat oleh Super Admin sistem saat
  instalasi awal).
- Data Yayasan sudah diisi.

## Langkah-langkah

1. Login sebagai admin yayasan, lalu buka menu **Lembaga**.

   ![Daftar Lembaga](images/00-01-daftar-lembaga.png)

2. Klik **Tambah Lembaga**. Isi data identitas lembaga: nama, jenjang (`bentuk_pendidikan`
   — TK/SD/SMP/SMA/SMK/dst), NPSN, NSS, alamat, kepala sekolah, dan data rekening.

   ![Form tambah Lembaga](images/00-02-form-lembaga.png)

3. Setelah Lembaga dibuat, buka menu **Pengguna** untuk membuat akun staf lembaga tersebut
   **kecuali Guru** — Kepala Sekolah, Admin Akademik, Admin Administrasi, Admin Keuangan.

   ![Daftar Pengguna](images/00-03-daftar-user.png)

4. Saat membuat pengguna baru, pilih Lembaga yang bersangkutan dan pilih role yang sesuai
   perannya. Untuk yang akan mengelola data akademik (Kelas, Siswa, Jadwal, Presensi,
   Asesmen), pilih role **Admin Akademik**.

   ![Form tambah pengguna dengan pilihan role](images/00-04-form-user-role.png)

5. **Akun Guru dibuat lewat menu terpisah: Data Guru** — bukan dari menu Pengguna. Satu
   form di sini langsung membuat akun login sekaligus profil guru lengkap (identitas,
   kepegawaian, dll) dalam satu langkah.

   ![Daftar Data Guru](images/00-05-daftar-guru.png)

6. Klik **Tambah Data Guru**, isi profilnya. **Email** menjadi username login, dan **NIP**
   otomatis menjadi password awal — keduanya wajib diisi.

   ![Form Tambah Data Guru](images/00-06-form-guru.png)

7. Jika yayasan menaungi lebih dari satu Lembaga, gunakan **pemilih lembaga (switcher)** di
   navbar untuk berpindah konteks — hampir semua halaman admin lembaga-scoped hanya
   menampilkan data lembaga yang sedang aktif dipilih.

## Kesalahan umum

- **Lupa memilih Lembaga aktif lewat switcher.** Sebagai admin yayasan, beberapa halaman
  pengaturan (mis. Pengaturan Akademik) mengharuskan satu Lembaga aktif dipilih dulu —
  kalau belum, halaman akan mengarahkan balik ke dashboard dengan pesan error, bukan
  crash.
- **Membuat pengguna tanpa memilih Lembaga.** Role seperti Admin Akademik wajib terikat ke
  satu Lembaga (`lembaga_id`) — tanpa itu mereka tidak akan melihat data apa pun saat
  login.
- **Membuat akun Guru lewat menu Pengguna, bukan menu Data Guru.** Menu Pengguna hanya
  membuat akun login (`User`) — untuk role Guru itu tidak cukup, karena presensi, jadwal,
  dan asesmen semuanya butuh profil Guru yang tertaut ke akun tsb. Akun yang dibuat lewat
  menu Pengguna dengan role Guru tidak akan punya profil ini dan tidak akan bisa memakai
  fitur guru sama sekali — selalu pakai menu **Data Guru** untuk akun Guru.
