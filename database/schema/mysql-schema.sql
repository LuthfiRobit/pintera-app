/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`),
  KEY `activity_log_log_name_event_index` (`log_name`,`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `akun_pendaftar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `akun_pendaftar` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_hp_wa` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `akun_pendaftar_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `akun_pendaftar_password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `akun_pendaftar_password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `alamat_calon_murid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alamat_calon_murid` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `calon_murid_id` bigint unsigned NOT NULL,
  `alamat_jalan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rt` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rw` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dusun` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `desa_kelurahan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kecamatan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kabupaten_kota` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provinsi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode_pos` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `alamat_calon_murid_calon_murid_id_unique` (`calon_murid_id`),
  CONSTRAINT `alamat_calon_murid_calon_murid_id_foreign` FOREIGN KEY (`calon_murid_id`) REFERENCES `calon_murid` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approval_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `approval_request_id` bigint unsigned NOT NULL,
  `workflow_step_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approval_logs_approval_request_id_foreign` (`approval_request_id`),
  KEY `approval_logs_workflow_step_id_foreign` (`workflow_step_id`),
  KEY `approval_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `approval_logs_approval_request_id_foreign` FOREIGN KEY (`approval_request_id`) REFERENCES `approval_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approval_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approval_logs_workflow_step_id_foreign` FOREIGN KEY (`workflow_step_id`) REFERENCES `workflow_steps` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approval_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow_definition_id` bigint unsigned NOT NULL,
  `approvable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approvable_id` bigint unsigned NOT NULL,
  `requester_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requester_id` bigint unsigned DEFAULT NULL,
  `current_step_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `last_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approval_requests_workflow_definition_id_foreign` (`workflow_definition_id`),
  KEY `approval_requests_approvable_type_approvable_id_index` (`approvable_type`,`approvable_id`),
  KEY `approval_requests_requester_type_requester_id_index` (`requester_type`,`requester_id`),
  KEY `approval_requests_current_step_id_foreign` (`current_step_id`),
  CONSTRAINT `approval_requests_current_step_id_foreign` FOREIGN KEY (`current_step_id`) REFERENCES `workflow_steps` (`id`) ON DELETE SET NULL,
  CONSTRAINT `approval_requests_workflow_definition_id_foreign` FOREIGN KEY (`workflow_definition_id`) REFERENCES `workflow_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asesmen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asesmen` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `subjek_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subjek_id` bigint unsigned NOT NULL,
  `guru_id` bigint unsigned NOT NULL,
  `kelas_id` bigint unsigned NOT NULL,
  `semester_id` bigint unsigned NOT NULL,
  `jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asesmen_guru_id_foreign` (`guru_id`),
  KEY `asesmen_kelas_id_foreign` (`kelas_id`),
  KEY `asesmen_semester_id_foreign` (`semester_id`),
  KEY `idx_asesmen_lmbg_kls_smt` (`lembaga_id`,`kelas_id`,`semester_id`),
  KEY `idx_asesmen_subjek` (`subjek_type`,`subjek_id`),
  CONSTRAINT `asesmen_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asesmen_kelas_id_foreign` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asesmen_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asesmen_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asesmen_komponen_penilaian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asesmen_komponen_penilaian` (
  `asesmen_id` bigint unsigned NOT NULL,
  `komponen_penilaian_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`asesmen_id`,`komponen_penilaian_id`),
  KEY `asesmen_komponen_penilaian_komponen_penilaian_id_foreign` (`komponen_penilaian_id`),
  CONSTRAINT `asesmen_komponen_penilaian_asesmen_id_foreign` FOREIGN KEY (`asesmen_id`) REFERENCES `asesmen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asesmen_komponen_penilaian_komponen_penilaian_id_foreign` FOREIGN KEY (`komponen_penilaian_id`) REFERENCES `komponen_penilaian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `aset_barang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aset_barang` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `kategori_aset_id` bigint unsigned NOT NULL,
  `ruangan_id` bigint unsigned NOT NULL,
  `kode_inventaris` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_barang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `merk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spesifikasi` text COLLATE utf8mb4_unicode_ci,
  `tipe_pencatatan` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unit',
  `qty` int unsigned NOT NULL DEFAULT '1',
  `satuan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unit',
  `kondisi` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'baik',
  `sumber_perolehan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'beli_lembaga',
  `tanggal_perolehan` date DEFAULT NULL,
  `harga_perolehan` decimal(15,2) DEFAULT NULL,
  `foto_barang_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `aset_barang_yayasan_id_foreign` (`yayasan_id`),
  KEY `aset_barang_kategori_aset_id_foreign` (`kategori_aset_id`),
  KEY `aset_barang_lembaga_id_kode_inventaris_index` (`lembaga_id`,`kode_inventaris`),
  KEY `aset_barang_ruangan_id_kategori_aset_id_index` (`ruangan_id`,`kategori_aset_id`),
  CONSTRAINT `aset_barang_kategori_aset_id_foreign` FOREIGN KEY (`kategori_aset_id`) REFERENCES `kategori_aset` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `aset_barang_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `aset_barang_ruangan_id_foreign` FOREIGN KEY (`ruangan_id`) REFERENCES `ruangan` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `aset_barang_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `pegawai_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pegawai_id` bigint unsigned NOT NULL,
  `attendance_point_id` bigint unsigned DEFAULT NULL,
  `method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `arah` enum('masuk','pulang') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu` datetime NOT NULL,
  `dicatat_oleh_user_id` bigint unsigned DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `attendance_events_pegawai_type_pegawai_id_index` (`pegawai_type`,`pegawai_id`),
  KEY `attendance_events_attendance_point_id_foreign` (`attendance_point_id`),
  KEY `attendance_events_dicatat_oleh_user_id_foreign` (`dicatat_oleh_user_id`),
  KEY `attendance_events_pegawai_type_pegawai_id_waktu_index` (`pegawai_type`,`pegawai_id`,`waktu`),
  KEY `attendance_events_lembaga_id_waktu_index` (`lembaga_id`,`waktu`),
  CONSTRAINT `attendance_events_attendance_point_id_foreign` FOREIGN KEY (`attendance_point_id`) REFERENCES `attendance_points` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_events_dicatat_oleh_user_id_foreign` FOREIGN KEY (`dicatat_oleh_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_events_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_method_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_method_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_method_config_unique` (`yayasan_id`,`lembaga_id`,`method`),
  KEY `attendance_method_configurations_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `attendance_method_configurations_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_method_configurations_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_points` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_points_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `attendance_points_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `jenis_ptk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jenis_karyawan_id` bigint unsigned DEFAULT NULL,
  `jam_masuk` time NOT NULL,
  `jam_pulang` time DEFAULT NULL,
  `toleransi_menit` int unsigned NOT NULL DEFAULT '0',
  `hari_kerja` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_policy_unique` (`yayasan_id`,`lembaga_id`,`jenis_ptk`,`jenis_karyawan_id`),
  KEY `attendance_policies_lembaga_id_foreign` (`lembaga_id`),
  KEY `attendance_policies_jenis_karyawan_id_foreign` (`jenis_karyawan_id`),
  CONSTRAINT `attendance_policies_jenis_karyawan_id_foreign` FOREIGN KEY (`jenis_karyawan_id`) REFERENCES `jenis_karyawan_master` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_policies_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_policies_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `pegawai_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pegawai_id` bigint unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_late` tinyint(1) NOT NULL DEFAULT '0',
  `late_minutes` int unsigned DEFAULT NULL,
  `waktu_masuk` datetime DEFAULT NULL,
  `waktu_pulang` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_record_pegawai_tanggal_unique` (`pegawai_type`,`pegawai_id`,`tanggal`),
  KEY `attendance_records_lembaga_id_foreign` (`lembaga_id`),
  KEY `attendance_records_pegawai_type_pegawai_id_index` (`pegawai_type`,`pegawai_id`),
  CONSTRAINT `attendance_records_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_job_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_job_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jenis_tagihan_id` bigint unsigned NOT NULL,
  `trigger_type` enum('cron','manual','event') COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `period` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bills_generated` int unsigned NOT NULL,
  `status` enum('success','partial','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_log` json DEFAULT NULL,
  `executed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `billing_job_logs_jenis_tagihan_id_foreign` (`jenis_tagihan_id`),
  CONSTRAINT `billing_job_logs_jenis_tagihan_id_foreign` FOREIGN KEY (`jenis_tagihan_id`) REFERENCES `jenis_tagihan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bri_inbound_payment_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bri_inbound_payment_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_request_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `va_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `pembayaran_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bri_inbound_payment_logs_payment_request_id_unique` (`payment_request_id`),
  KEY `bri_inbound_payment_logs_pembayaran_id_foreign` (`pembayaran_id`),
  CONSTRAINT `bri_inbound_payment_logs_pembayaran_id_foreign` FOREIGN KEY (`pembayaran_id`) REFERENCES `pembayaran` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bri_qris_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bri_qris_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pembayaran_id` bigint unsigned NOT NULL,
  `qris_type` enum('DIRECT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `qr_code` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expired_at` timestamp NOT NULL,
  `status` enum('WAITING','PAID','EXPIRED') COLLATE utf8mb4_unicode_ci NOT NULL,
  `callback_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bri_qris_payments_pembayaran_id_status_index` (`pembayaran_id`,`status`),
  CONSTRAINT `bri_qris_payments_pembayaran_id_foreign` FOREIGN KEY (`pembayaran_id`) REFERENCES `pembayaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bri_virtual_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bri_virtual_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pembayaran_id` bigint unsigned DEFAULT NULL,
  `wallet_id` bigint unsigned DEFAULT NULL,
  `va_type` enum('WALLET_PERMANENT','BILL_DIRECT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `va_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `status` enum('PERMANENT','WAITING','PAID','EXPIRED') COLLATE utf8mb4_unicode_ci NOT NULL,
  `callback_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bri_virtual_accounts_va_number_unique` (`va_number`),
  KEY `bri_virtual_accounts_pembayaran_id_foreign` (`pembayaran_id`),
  KEY `bri_virtual_accounts_wallet_id_va_type_index` (`wallet_id`,`va_type`),
  CONSTRAINT `bri_virtual_accounts_pembayaran_id_foreign` FOREIGN KEY (`pembayaran_id`) REFERENCES `pembayaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bri_virtual_accounts_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `calon_murid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calon_murid` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint unsigned NOT NULL,
  `yayasan_id` bigint unsigned NOT NULL,
  `no_kk` text COLLATE utf8mb4_unicode_ci,
  `nisn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `golongan_darah` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `calon_murid_yayasan_id_foreign` (`yayasan_id`),
  KEY `calon_murid_person_id_foreign` (`person_id`),
  CONSTRAINT `calon_murid_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `calon_murid_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `catatan_wali_kelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `catatan_wali_kelas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `siswa_id` bigint unsigned NOT NULL,
  `semester_id` bigint unsigned NOT NULL,
  `catatan_sikap` text COLLATE utf8mb4_unicode_ci,
  `catatan_perkembangan` text COLLATE utf8mb4_unicode_ci,
  `tinggi_badan_cm` decimal(5,1) DEFAULT NULL,
  `berat_badan_kg` decimal(5,1) DEFAULT NULL,
  `lingkar_kepala_cm` decimal(5,1) DEFAULT NULL,
  `ekstrakurikuler` json DEFAULT NULL,
  `prestasi` json DEFAULT NULL,
  `pkl_info` json DEFAULT NULL,
  `keterangan_kenaikan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catatan_wali_kelas_siswa_id_semester_id_unique` (`siswa_id`,`semester_id`),
  KEY `catatan_wali_kelas_lembaga_id_foreign` (`lembaga_id`),
  KEY `catatan_wali_kelas_semester_id_foreign` (`semester_id`),
  CONSTRAINT `catatan_wali_kelas_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `catatan_wali_kelas_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON DELETE CASCADE,
  CONSTRAINT `catatan_wali_kelas_siswa_id_foreign` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cicilan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cicilan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `skema_cicilan_id` bigint unsigned NOT NULL,
  `urutan` tinyint unsigned NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `jatuh_tempo` date NOT NULL,
  `status` enum('belum_bayar','menunggu_verifikasi','ditolak','lunas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_bayar',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cicilan_skema_cicilan_id_urutan_unique` (`skema_cicilan_id`,`urutan`),
  CONSTRAINT `cicilan_skema_cicilan_id_foreign` FOREIGN KEY (`skema_cicilan_id`) REFERENCES `skema_cicilan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_khusus_calon_murid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_khusus_calon_murid` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `calon_murid_id` bigint unsigned NOT NULL,
  `kepemilikan_kip` tinyint(1) NOT NULL DEFAULT '0',
  `nomor_kip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `riwayat_beasiswa` text COLLATE utf8mb4_unicode_ci,
  `kebutuhan_khusus` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_khusus_calon_murid_calon_murid_id_unique` (`calon_murid_id`),
  CONSTRAINT `data_khusus_calon_murid_calon_murid_id_foreign` FOREIGN KEY (`calon_murid_id`) REFERENCES `calon_murid` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_periodik_calon_murid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_periodik_calon_murid` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `calon_murid_id` bigint unsigned NOT NULL,
  `tinggi_badan_cm` smallint unsigned DEFAULT NULL,
  `berat_badan_kg` smallint unsigned DEFAULT NULL,
  `jarak_tempuh_km` smallint unsigned DEFAULT NULL,
  `waktu_tempuh_menit` smallint unsigned DEFAULT NULL,
  `jumlah_saudara_kandung` tinyint unsigned DEFAULT NULL,
  `alat_transportasi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_periodik_calon_murid_calon_murid_id_unique` (`calon_murid_id`),
  CONSTRAINT `data_periodik_calon_murid_calon_murid_id_foreign` FOREIGN KEY (`calon_murid_id`) REFERENCES `calon_murid` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dokumen_pendaftaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dokumen_pendaftaran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pendaftaran_id` bigint unsigned NOT NULL,
  `dokumen_syarat_ppdb_id` bigint unsigned NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_file_asli` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ukuran_bytes` int unsigned NOT NULL,
  `status_verifikasi` enum('belum_diverifikasi','diterima','ditolak') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_diverifikasi',
  `catatan_verifikasi` text COLLATE utf8mb4_unicode_ci,
  `diverifikasi_oleh_user_id` bigint unsigned DEFAULT NULL,
  `diverifikasi_pada` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dokumen_pendaftaran_dokumen_syarat_ppdb_id_foreign` (`dokumen_syarat_ppdb_id`),
  KEY `dokumen_pendaftaran_diverifikasi_oleh_user_id_foreign` (`diverifikasi_oleh_user_id`),
  KEY `dokumen_pendaftaran_pendaftaran_id_status_verifikasi_index` (`pendaftaran_id`,`status_verifikasi`),
  CONSTRAINT `dokumen_pendaftaran_diverifikasi_oleh_user_id_foreign` FOREIGN KEY (`diverifikasi_oleh_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dokumen_pendaftaran_dokumen_syarat_ppdb_id_foreign` FOREIGN KEY (`dokumen_syarat_ppdb_id`) REFERENCES `dokumen_syarat_ppdb` (`id`),
  CONSTRAINT `dokumen_pendaftaran_pendaftaran_id_foreign` FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dokumen_syarat_ppdb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dokumen_syarat_ppdb` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jalur_ppdb_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned NOT NULL,
  `nama_dokumen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wajib` tinyint(1) NOT NULL DEFAULT '1',
  `urutan` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dokumen_syarat_ppdb_jalur_ppdb_id_foreign` (`jalur_ppdb_id`),
  KEY `dokumen_syarat_ppdb_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `dokumen_syarat_ppdb_jalur_ppdb_id_foreign` FOREIGN KEY (`jalur_ppdb_id`) REFERENCES `jalur_ppdb` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dokumen_syarat_ppdb_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ekstrakurikuler_lembaga`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ekstrakurikuler_lembaga` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `jenis_ekskul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_ekskul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_sk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_sk` date DEFAULT NULL,
  `jam_per_minggu` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ekstrakurikuler_lembaga_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `ekstrakurikuler_lembaga_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `elemen_cp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `elemen_cp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_urut` tinyint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `elemen_cp_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_qr_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_qr_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pegawai_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pegawai_id` bigint unsigned NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_qr_codes_token_unique` (`token`),
  KEY `employee_qr_codes_pegawai_type_pegawai_id_index` (`pegawai_type`,`pegawai_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fase`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fase` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `urutan` tinyint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fase_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fase_default_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fase_default_mapping` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `bentuk_pendidikan` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tingkat` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fase_id` bigint unsigned NOT NULL,
  `lembaga_key` bigint unsigned GENERATED ALWAYS AS (coalesce(`lembaga_id`,0)) VIRTUAL,
  `tingkat_key` varchar(10) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (coalesce(`tingkat`,_utf8mb4'*')) VIRTUAL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fase_default_mapping_scope_unique` (`lembaga_key`,`bentuk_pendidikan`,`tingkat_key`),
  KEY `fase_default_mapping_lembaga_id_foreign` (`lembaga_id`),
  KEY `fase_default_mapping_fase_id_foreign` (`fase_id`),
  CONSTRAINT `fase_default_mapping_fase_id_foreign` FOREIGN KEY (`fase_id`) REFERENCES `fase` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fase_default_mapping_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `formulir_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `formulir_field` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jalur_ppdb_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type` enum('text','textarea','number','date','select','file') COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` json DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `urutan` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `formulir_field_jalur_ppdb_id_foreign` (`jalur_ppdb_id`),
  KEY `formulir_field_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `formulir_field_jalur_ppdb_id_foreign` FOREIGN KEY (`jalur_ppdb_id`) REFERENCES `jalur_ppdb` (`id`) ON DELETE CASCADE,
  CONSTRAINT `formulir_field_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gedung`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gedung` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `kode_gedung` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_gedung` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_lantai` int unsigned NOT NULL DEFAULT '1',
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `is_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gedung_yayasan_id_foreign` (`yayasan_id`),
  KEY `gedung_lembaga_id_is_aktif_index` (`lembaga_id`,`is_aktif`),
  CONSTRAINT `gedung_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gedung_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gelombang_jalur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gelombang_jalur` (
  `gelombang_ppdb_id` bigint unsigned NOT NULL,
  `jalur_ppdb_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`gelombang_ppdb_id`,`jalur_ppdb_id`),
  KEY `gelombang_jalur_jalur_ppdb_id_foreign` (`jalur_ppdb_id`),
  CONSTRAINT `gelombang_jalur_gelombang_ppdb_id_foreign` FOREIGN KEY (`gelombang_ppdb_id`) REFERENCES `gelombang_ppdb` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gelombang_jalur_jalur_ppdb_id_foreign` FOREIGN KEY (`jalur_ppdb_id`) REFERENCES `jalur_ppdb` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gelombang_ppdb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gelombang_ppdb` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `tahun_ajaran_id` bigint unsigned NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_buka` date NOT NULL,
  `tanggal_tutup` date NOT NULL,
  `kuota` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gelombang_ppdb_tahun_ajaran_id_nama_unique` (`tahun_ajaran_id`,`nama`),
  KEY `gelombang_ppdb_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `gelombang_ppdb_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gelombang_ppdb_tahun_ajaran_id_foreign` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `guru`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guru` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned NOT NULL,
  `nuptk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jenis_ptk` enum('guru_kelas','guru_mapel','kepala_sekolah','tenaga_administrasi','guru_bk') COLLATE utf8mb4_unicode_ci NOT NULL,
  `kapasitas_kasus_aktif` int DEFAULT NULL,
  `status_kepegawaian` enum('PNS','PPPK','GTY','PTY','Honorer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `golongan_pangkat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tmt_tugas` date DEFAULT NULL,
  `tmt_pns` date DEFAULT NULL,
  `status_aktif` enum('aktif','non_aktif','mutasi','pensiun') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_guru_person_lembaga` (`person_id`,`lembaga_id`),
  UNIQUE KEY `guru_nuptk_unique` (`nuptk`),
  KEY `guru_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `guru_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guru_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `guru_jabatan_tambahan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guru_jabatan_tambahan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `guru_id` bigint unsigned NOT NULL,
  `jabatan_tambahan_master_id` bigint unsigned NOT NULL,
  `mulai_periode` date NOT NULL,
  `akhir_periode` date DEFAULT NULL,
  `no_sk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guru_jabatan_tambahan_guru_id_foreign` (`guru_id`),
  KEY `guru_jabatan_tambahan_jabatan_tambahan_master_id_foreign` (`jabatan_tambahan_master_id`),
  CONSTRAINT `guru_jabatan_tambahan_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guru_jabatan_tambahan_jabatan_tambahan_master_id_foreign` FOREIGN KEY (`jabatan_tambahan_master_id`) REFERENCES `jabatan_tambahan_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_seleksi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_seleksi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pendaftaran_id` bigint unsigned NOT NULL,
  `seleksi_ppdb_id` bigint unsigned NOT NULL,
  `nilai` decimal(5,2) DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `dinilai_oleh_user_id` bigint unsigned DEFAULT NULL,
  `dinilai_pada` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hasil_seleksi_pendaftaran_id_seleksi_ppdb_id_unique` (`pendaftaran_id`,`seleksi_ppdb_id`),
  KEY `hasil_seleksi_seleksi_ppdb_id_foreign` (`seleksi_ppdb_id`),
  KEY `hasil_seleksi_dinilai_oleh_user_id_foreign` (`dinilai_oleh_user_id`),
  CONSTRAINT `hasil_seleksi_dinilai_oleh_user_id_foreign` FOREIGN KEY (`dinilai_oleh_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hasil_seleksi_pendaftaran_id_foreign` FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hasil_seleksi_seleksi_ppdb_id_foreign` FOREIGN KEY (`seleksi_ppdb_id`) REFERENCES `seleksi_ppdb` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jabatan_tambahan_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jabatan_tambahan_master` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kelompok` enum('struktural','fungsional') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jadwal_pelajaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jadwal_pelajaran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `kelas_id` bigint unsigned NOT NULL,
  `jam_pelajaran_id` bigint unsigned NOT NULL,
  `ruangan_id` bigint unsigned DEFAULT NULL,
  `mata_pelajaran_id` bigint unsigned DEFAULT NULL,
  `guru_id` bigint unsigned NOT NULL,
  `semester_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jadwal_pelajaran_kelas_id_jam_pelajaran_id_semester_id_unique` (`kelas_id`,`jam_pelajaran_id`,`semester_id`),
  KEY `jadwal_pelajaran_jam_pelajaran_id_foreign` (`jam_pelajaran_id`),
  KEY `jadwal_pelajaran_mata_pelajaran_id_foreign` (`mata_pelajaran_id`),
  KEY `jadwal_pelajaran_guru_id_foreign` (`guru_id`),
  KEY `jadwal_pelajaran_semester_id_foreign` (`semester_id`),
  KEY `idx_jadwal_lmbg_kls_smt` (`lembaga_id`,`kelas_id`,`semester_id`),
  KEY `jadwal_pelajaran_ruangan_id_foreign` (`ruangan_id`),
  CONSTRAINT `jadwal_pelajaran_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwal_pelajaran_jam_pelajaran_id_foreign` FOREIGN KEY (`jam_pelajaran_id`) REFERENCES `jam_pelajaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwal_pelajaran_kelas_id_foreign` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwal_pelajaran_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwal_pelajaran_mata_pelajaran_id_foreign` FOREIGN KEY (`mata_pelajaran_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jadwal_pelajaran_ruangan_id_foreign` FOREIGN KEY (`ruangan_id`) REFERENCES `ruangan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jadwal_pelajaran_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jalur_ppdb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jalur_ppdb` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `tahun_ajaran_id` bigint unsigned NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `status_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jalur_ppdb_tahun_ajaran_id_nama_unique` (`tahun_ajaran_id`,`nama`),
  KEY `jalur_ppdb_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `jalur_ppdb_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jalur_ppdb_tahun_ajaran_id_foreign` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jam_pelajaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jam_pelajaran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pola_jam_id` bigint unsigned NOT NULL,
  `hari` enum('senin','selasa','rabu','kamis','jumat','sabtu','minggu') COLLATE utf8mb4_unicode_ci NOT NULL,
  `urutan` int unsigned NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `is_pelajaran` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jam_pelajaran_pola_jam_id_hari_urutan_unique` (`pola_jam_id`,`hari`,`urutan`),
  CONSTRAINT `jam_pelajaran_pola_jam_id_foreign` FOREIGN KEY (`pola_jam_id`) REFERENCES `pola_jam` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jawaban_formulir_pendaftaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jawaban_formulir_pendaftaran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pendaftaran_id` bigint unsigned NOT NULL,
  `formulir_field_id` bigint unsigned NOT NULL,
  `nilai` text COLLATE utf8mb4_unicode_ci,
  `nama_file_asli` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ukuran_bytes` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jawaban_formulir_pendaftaran_formulir_field_id_foreign` (`formulir_field_id`),
  KEY `idx_jawaban_form_pendaftaran_field` (`pendaftaran_id`,`formulir_field_id`),
  CONSTRAINT `jawaban_formulir_pendaftaran_formulir_field_id_foreign` FOREIGN KEY (`formulir_field_id`) REFERENCES `formulir_field` (`id`),
  CONSTRAINT `jawaban_formulir_pendaftaran_pendaftaran_id_foreign` FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_karyawan_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_karyawan_master` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_konselor` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_shift`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_shift` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_pulang` time NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jenis_shift_yayasan_id_foreign` (`yayasan_id`),
  KEY `jenis_shift_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `jenis_shift_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jenis_shift_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_tagihan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_tagihan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori` enum('pendaftaran','daftar_ulang','lainnya','spp','tahunan','kegiatan','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority_score` int unsigned DEFAULT NULL,
  `default_amount` decimal(12,2) DEFAULT NULL,
  `mode` enum('manual','otomatis') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `tanggal_generate` tinyint unsigned DEFAULT NULL,
  `hari_jatuh_tempo` tinyint unsigned DEFAULT NULL,
  `va_expire_hours` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_generated_period` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bisa_dicicil` tinyint(1) NOT NULL DEFAULT '0',
  `maks_cicilan` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jenis_tagihan_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `jenis_tagihan_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_tagihan_keringanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_tagihan_keringanan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jenis_tagihan_id` bigint unsigned NOT NULL,
  `kategori_keringanan_id` bigint unsigned NOT NULL,
  `tipe_potongan` enum('fixed','persen') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nilai` decimal(12,2) NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jenis_tagihan_keringanan_unique` (`jenis_tagihan_id`,`kategori_keringanan_id`),
  KEY `jenis_tagihan_keringanan_kategori_keringanan_id_foreign` (`kategori_keringanan_id`),
  CONSTRAINT `jenis_tagihan_keringanan_jenis_tagihan_id_foreign` FOREIGN KEY (`jenis_tagihan_id`) REFERENCES `jenis_tagihan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jenis_tagihan_keringanan_kategori_keringanan_id_foreign` FOREIGN KEY (`kategori_keringanan_id`) REFERENCES `kategori_keringanan` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_tagihan_sasaran_grup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_tagihan_sasaran_grup` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jenis_tagihan_id` bigint unsigned NOT NULL,
  `tipe` enum('sasaran','tarif') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nominal` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jenis_tagihan_sasaran_grup_jenis_tagihan_id_foreign` (`jenis_tagihan_id`),
  CONSTRAINT `jenis_tagihan_sasaran_grup_jenis_tagihan_id_foreign` FOREIGN KEY (`jenis_tagihan_id`) REFERENCES `jenis_tagihan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_tagihan_sasaran_kriteria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_tagihan_sasaran_kriteria` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jenis_tagihan_sasaran_grup_id` bigint unsigned NOT NULL,
  `field` enum('lembaga','tahun_ajaran','tingkat','kelas','jenis_kelamin','status_siswa') COLLATE utf8mb4_unicode_ci NOT NULL,
  `operator` enum('in','not_in') COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_kriteria_grup` (`jenis_tagihan_sasaran_grup_id`),
  CONSTRAINT `fk_kriteria_grup` FOREIGN KEY (`jenis_tagihan_sasaran_grup_id`) REFERENCES `jenis_tagihan_sasaran_grup` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_tes_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jenis_tes_master` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jenis_tes_master_lembaga_id_nama_unique` (`lembaga_id`,`nama`),
  CONSTRAINT `jenis_tes_master_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kalender_akademik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kalender_akademik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `tanggal` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe` enum('libur','kerja') COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kalender_akademik_lembaga_id_tanggal_index` (`lembaga_id`,`tanggal`),
  CONSTRAINT `kalender_akademik_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kalender_kerja_sdm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kalender_kerja_sdm` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `tanggal` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kalender_kerja_sdm_lembaga_id_tanggal_index` (`lembaga_id`,`tanggal`),
  KEY `kalender_kerja_sdm_yayasan_id_tanggal_index` (`yayasan_id`,`tanggal`),
  CONSTRAINT `kalender_kerja_sdm_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kalender_kerja_sdm_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `karyawan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `karyawan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint unsigned NOT NULL,
  `yayasan_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `jenis_karyawan_id` bigint unsigned NOT NULL,
  `status_aktif` enum('aktif','non_aktif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `kapasitas_kasus_aktif` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `karyawan_yayasan_id_foreign` (`yayasan_id`),
  KEY `karyawan_lembaga_id_foreign` (`lembaga_id`),
  KEY `karyawan_jenis_karyawan_id_foreign` (`jenis_karyawan_id`),
  KEY `karyawan_person_id_foreign` (`person_id`),
  CONSTRAINT `karyawan_jenis_karyawan_id_foreign` FOREIGN KEY (`jenis_karyawan_id`) REFERENCES `jenis_karyawan_master` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `karyawan_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `karyawan_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `karyawan_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kasus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kasus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `siswa_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned NOT NULL,
  `diajukan_oleh_guru_id` bigint unsigned DEFAULT NULL,
  `diajukan_oleh_orang_tua_id` bigint unsigned DEFAULT NULL,
  `kategori_masalah` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `lampiran` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tingkat_urgensi` enum('rendah','sedang','tinggi') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('diajukan','menunggu_consent','ditugaskan','berjalan','eskalasi','selesai') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'diajukan',
  `konselor_guru_id` bigint unsigned DEFAULT NULL,
  `konselor_karyawan_id` bigint unsigned DEFAULT NULL,
  `dikonfirmasi_pihak_lain_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kasus_siswa_id_foreign` (`siswa_id`),
  KEY `kasus_lembaga_id_foreign` (`lembaga_id`),
  KEY `kasus_diajukan_oleh_guru_id_foreign` (`diajukan_oleh_guru_id`),
  KEY `kasus_diajukan_oleh_orang_tua_id_foreign` (`diajukan_oleh_orang_tua_id`),
  KEY `kasus_konselor_guru_id_foreign` (`konselor_guru_id`),
  KEY `kasus_konselor_karyawan_id_foreign` (`konselor_karyawan_id`),
  CONSTRAINT `kasus_diajukan_oleh_guru_id_foreign` FOREIGN KEY (`diajukan_oleh_guru_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kasus_diajukan_oleh_orang_tua_id_foreign` FOREIGN KEY (`diajukan_oleh_orang_tua_id`) REFERENCES `orang_tua` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kasus_konselor_guru_id_foreign` FOREIGN KEY (`konselor_guru_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kasus_konselor_karyawan_id_foreign` FOREIGN KEY (`konselor_karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kasus_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kasus_siswa_id_foreign` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kasus_consent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kasus_consent` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kasus_id` bigint unsigned NOT NULL,
  `jenis` enum('sesi_pendampingan','pengumpulan_media') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('menunggu','disetujui') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `disetujui_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kasus_consent_kasus_id_jenis_unique` (`kasus_id`,`jenis`),
  CONSTRAINT `kasus_consent_kasus_id_foreign` FOREIGN KEY (`kasus_id`) REFERENCES `kasus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kasus_evaluasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kasus_evaluasi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kasus_id` bigint unsigned NOT NULL,
  `tanggal` datetime NOT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `keputusan` enum('lanjut','eskalasi','selesai') COLLATE utf8mb4_unicode_ci NOT NULL,
  `dibuat_oleh_user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kasus_evaluasi_kasus_id_foreign` (`kasus_id`),
  KEY `kasus_evaluasi_dibuat_oleh_user_id_foreign` (`dibuat_oleh_user_id`),
  CONSTRAINT `kasus_evaluasi_dibuat_oleh_user_id_foreign` FOREIGN KEY (`dibuat_oleh_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kasus_evaluasi_kasus_id_foreign` FOREIGN KEY (`kasus_id`) REFERENCES `kasus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kasus_sesi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kasus_sesi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kasus_id` bigint unsigned NOT NULL,
  `dijadwalkan_pada` datetime NOT NULL,
  `peserta` enum('siswa','orang_tua','keduanya') COLLATE utf8mb4_unicode_ci NOT NULL,
  `lokasi_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('terjadwal','selesai','batal','tidak_hadir') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'terjadwal',
  `alasan_batal` text COLLATE utf8mb4_unicode_ci,
  `catatan_internal` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kasus_sesi_kasus_id_foreign` (`kasus_id`),
  CONSTRAINT `kasus_sesi_kasus_id_foreign` FOREIGN KEY (`kasus_id`) REFERENCES `kasus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kasus_tugas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kasus_tugas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kasus_id` bigint unsigned NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instruksi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `frekuensi` enum('sekali','harian','mingguan','bulanan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_urutan` int unsigned NOT NULL DEFAULT '1',
  `batch_total` int unsigned NOT NULL DEFAULT '1',
  `mulai_pada` date NOT NULL,
  `batas_selesai_pada` date NOT NULL,
  `status` enum('ditugaskan','dikerjakan','revisi','selesai','terlewat') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ditugaskan',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kasus_tugas_kasus_id_foreign` (`kasus_id`),
  CONSTRAINT `kasus_tugas_kasus_id_foreign` FOREIGN KEY (`kasus_id`) REFERENCES `kasus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kasus_tugas_submission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kasus_tugas_submission` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tugas_id` bigint unsigned NOT NULL,
  `siswa_id` bigint unsigned DEFAULT NULL,
  `orang_tua_id` bigint unsigned DEFAULT NULL,
  `teks` text COLLATE utf8mb4_unicode_ci,
  `lampiran` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_review` enum('menunggu_review','diterima','revisi_diminta') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu_review',
  `catatan_revisi` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kasus_tugas_submission_tugas_id_foreign` (`tugas_id`),
  KEY `kasus_tugas_submission_siswa_id_foreign` (`siswa_id`),
  KEY `kasus_tugas_submission_orang_tua_id_foreign` (`orang_tua_id`),
  CONSTRAINT `kasus_tugas_submission_orang_tua_id_foreign` FOREIGN KEY (`orang_tua_id`) REFERENCES `orang_tua` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kasus_tugas_submission_siswa_id_foreign` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kasus_tugas_submission_tugas_id_foreign` FOREIGN KEY (`tugas_id`) REFERENCES `kasus_tugas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kategori_aset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kategori_aset` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `kode_kategori` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_kategori` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kategori_aset_yayasan_id_foreign` (`yayasan_id`),
  KEY `kategori_aset_lembaga_id_kode_kategori_index` (`lembaga_id`,`kode_kategori`),
  CONSTRAINT `kategori_aset_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kategori_aset_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kategori_keringanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kategori_keringanan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kategori_keringanan_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `kategori_keringanan_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kelas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `tahun_ajaran_id` bigint unsigned NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tingkat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fase_id` bigint unsigned DEFAULT NULL,
  `kurikulum` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pola_jam_id` bigint unsigned DEFAULT NULL,
  `ruangan_id` bigint unsigned DEFAULT NULL,
  `wali_kelas_guru_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kelas_tahun_ajaran_id_nama_unique` (`tahun_ajaran_id`,`nama`),
  KEY `kelas_wali_kelas_guru_id_foreign` (`wali_kelas_guru_id`),
  KEY `idx_kelas_lembaga_ta` (`lembaga_id`,`tahun_ajaran_id`),
  KEY `kelas_pola_jam_id_index` (`pola_jam_id`),
  KEY `kelas_ruangan_id_foreign` (`ruangan_id`),
  KEY `kelas_fase_id_foreign` (`fase_id`),
  CONSTRAINT `kelas_fase_id_foreign` FOREIGN KEY (`fase_id`) REFERENCES `fase` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kelas_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kelas_ruangan_id_foreign` FOREIGN KEY (`ruangan_id`) REFERENCES `ruangan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kelas_tahun_ajaran_id_foreign` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kelas_wali_kelas_guru_id_foreign` FOREIGN KEY (`wali_kelas_guru_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `keluarga_calon_murid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `keluarga_calon_murid` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `calon_murid_id` bigint unsigned NOT NULL,
  `jenis` enum('ayah','ibu','wali') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nik` text COLLATE utf8mb4_unicode_ci,
  `tahun_lahir` smallint unsigned DEFAULT NULL,
  `pendidikan_terakhir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pekerjaan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `penghasilan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `keluarga_calon_murid_calon_murid_id_foreign` (`calon_murid_id`),
  CONSTRAINT `keluarga_calon_murid_calon_murid_id_foreign` FOREIGN KEY (`calon_murid_id`) REFERENCES `calon_murid` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `komponen_penilaian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `komponen_penilaian` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `subjek_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subjek_id` bigint unsigned NOT NULL,
  `assessment_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'numeric',
  `semester_id` bigint unsigned NOT NULL,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `bobot` tinyint unsigned NOT NULL DEFAULT '10',
  `kktp` text COLLATE utf8mb4_unicode_ci,
  `kktp_minimal` tinyint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `komponen_penilaian_semester_id_foreign` (`semester_id`),
  KEY `idx_komp_lmbg_mapel_smt` (`lembaga_id`,`semester_id`),
  KEY `idx_komp_subjek` (`subjek_type`,`subjek_id`),
  CONSTRAINT `komponen_penilaian_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `komponen_penilaian_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kuota_cuti_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kuota_cuti_config` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `jenis_ptk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jenis_karyawan_id` bigint unsigned DEFAULT NULL,
  `jatah_hari_per_tahun` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kuota_cuti_config_unique` (`yayasan_id`,`lembaga_id`,`jenis_ptk`,`jenis_karyawan_id`),
  KEY `kuota_cuti_config_lembaga_id_foreign` (`lembaga_id`),
  KEY `kuota_cuti_config_jenis_karyawan_id_foreign` (`jenis_karyawan_id`),
  CONSTRAINT `kuota_cuti_config_jenis_karyawan_id_foreign` FOREIGN KEY (`jenis_karyawan_id`) REFERENCES `jenis_karyawan_master` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kuota_cuti_config_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kuota_cuti_config_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kurikulum_assignment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kurikulum_assignment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `tahun_ajaran_id` bigint unsigned NOT NULL,
  `bentuk_pendidikan` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tingkat` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kurikulum` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lembaga_key` bigint unsigned GENERATED ALWAYS AS (coalesce(`lembaga_id`,0)) VIRTUAL,
  `tingkat_key` varchar(10) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (coalesce(`tingkat`,_utf8mb4'*')) VIRTUAL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kurikulum_assignment_scope_unique` (`lembaga_key`,`tahun_ajaran_id`,`bentuk_pendidikan`,`tingkat_key`),
  KEY `kurikulum_assignment_lembaga_id_foreign` (`lembaga_id`),
  KEY `kurikulum_assignment_tahun_ajaran_id_foreign` (`tahun_ajaran_id`),
  CONSTRAINT `kurikulum_assignment_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kurikulum_assignment_tahun_ajaran_id_foreign` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `layanan_khusus_lembaga`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `layanan_khusus_lembaga` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `jenis_layanan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_sk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tmt` date DEFAULT NULL,
  `tst` date DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `layanan_khusus_lembaga_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `layanan_khusus_lembaga_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lembaga`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lembaga` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `npsn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nss` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode_lembaga` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bentuk_pendidikan` enum('KB','TPA','SPS','TK','SD','SMP','SMA','SMK','SLB') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_sekolah` enum('negeri','swasta') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_kepemilikan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `naungan` enum('kemendikdasmen','kemenag') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sk_pendirian_nomor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sk_pendirian_tanggal` date DEFAULT NULL,
  `sk_izin_operasional_nomor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sk_izin_operasional_tanggal` date DEFAULT NULL,
  `akreditasi` enum('A','B','C','belum') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum',
  `sk_akreditasi_nomor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_sk_akreditasi` date DEFAULT NULL,
  `nama_kepala_sekolah` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_bendahara_bosp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat_jalan` text COLLATE utf8mb4_unicode_ci,
  `rt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rw` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_dusun` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `desa_kelurahan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kecamatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kabupaten_kota` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provinsi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kode_pos` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lintang` decimal(10,7) DEFAULT NULL,
  `bujur` decimal(10,7) DEFAULT NULL,
  `telepon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fax` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_bank` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cabang_kcp_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rekening_atas_nama` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nomor_rekening` text COLLATE utf8mb4_unicode_ci,
  `mbs` tinyint(1) NOT NULL DEFAULT '0',
  `nama_wajib_pajak` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `npwp` text COLLATE utf8mb4_unicode_ci,
  `status_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `hari_libur_mingguan` json NOT NULL DEFAULT (json_array(0)),
  `hari_libur_mingguan_sdm` json NOT NULL DEFAULT (json_array(0)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lembaga_npsn_unique` (`npsn`),
  UNIQUE KEY `lembaga_slug_unique` (`slug`),
  UNIQUE KEY `lembaga_kode_lembaga_unique` (`kode_lembaga`),
  KEY `lembaga_yayasan_id_foreign` (`yayasan_id`),
  KEY `lembaga_status_aktif_yayasan_id_index` (`status_aktif`,`yayasan_id`),
  CONSTRAINT `lembaga_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lembaga_data_periodik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lembaga_data_periodik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `semester_id` bigint unsigned NOT NULL,
  `waktu_penyelenggaraan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sumber_listrik` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `daya_listrik` int unsigned DEFAULT NULL,
  `akses_internet` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_bos` tinyint(1) NOT NULL DEFAULT '0',
  `sertifikasi_iso` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ketersediaan_air_bersih` tinyint(1) NOT NULL DEFAULT '0',
  `kecukupan_air_bersih` tinyint(1) NOT NULL DEFAULT '0',
  `jumlah_tempat_cuci_tangan` int unsigned NOT NULL DEFAULT '0',
  `jumlah_jamban` int unsigned NOT NULL DEFAULT '0',
  `stratifikasi_uks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_kie_sanitasi` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lembaga_data_periodik_lembaga_id_semester_id_unique` (`lembaga_id`,`semester_id`),
  KEY `lembaga_data_periodik_semester_id_foreign` (`semester_id`),
  CONSTRAINT `lembaga_data_periodik_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lembaga_data_periodik_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lpj_pengadaan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lpj_pengadaan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pengajuan_pengadaan_id` bigint unsigned NOT NULL,
  `total_realisasi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `selisih_dana` decimal(15,2) NOT NULL DEFAULT '0.00',
  `bukti_kembali_sisa_dana_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_lpj` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `catatan_verifikasi` text COLLATE utf8mb4_unicode_ci,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lpj_pengadaan_pengajuan_pengadaan_id_unique` (`pengajuan_pengadaan_id`),
  KEY `lpj_pengadaan_verified_by_user_id_foreign` (`verified_by_user_id`),
  CONSTRAINT `lpj_pengadaan_pengajuan_pengadaan_id_foreign` FOREIGN KEY (`pengajuan_pengadaan_id`) REFERENCES `pengajuan_pengadaan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lpj_pengadaan_verified_by_user_id_foreign` FOREIGN KEY (`verified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lpj_pengadaan_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lpj_pengadaan_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lpj_pengadaan_id` bigint unsigned NOT NULL,
  `pengajuan_item_id` bigint unsigned NOT NULL,
  `harga_satuan_riil` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_riil` decimal(15,2) NOT NULL DEFAULT '0.00',
  `foto_nota_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_fisik_barang_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_konversi_sarpras` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lpj_pengadaan_item_lpj_pengadaan_id_foreign` (`lpj_pengadaan_id`),
  KEY `lpj_pengadaan_item_pengajuan_item_id_foreign` (`pengajuan_item_id`),
  CONSTRAINT `lpj_pengadaan_item_lpj_pengadaan_id_foreign` FOREIGN KEY (`lpj_pengadaan_id`) REFERENCES `lpj_pengadaan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lpj_pengadaan_item_pengajuan_item_id_foreign` FOREIGN KEY (`pengajuan_item_id`) REFERENCES `pengajuan_pengadaan_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manual_payment_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `manual_payment_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pembayaran_id` bigint unsigned NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transfer_proof_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_origin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_date` date NOT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `manual_payment_requests_pembayaran_id_foreign` (`pembayaran_id`),
  KEY `manual_payment_requests_requested_by_foreign` (`requested_by`),
  KEY `manual_payment_requests_reviewed_by_foreign` (`reviewed_by`),
  CONSTRAINT `manual_payment_requests_pembayaran_id_foreign` FOREIGN KEY (`pembayaran_id`) REFERENCES `pembayaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `manual_payment_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `manual_payment_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mata_pelajaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mata_pelajaran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `kode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_urut` smallint unsigned NOT NULL DEFAULT '1',
  `tipe` enum('mapel') COLLATE utf8mb4_unicode_ci NOT NULL,
  `kelompok` enum('umum','agama_kemenag','pilihan','kejuruan','mulok','projek_p5_ppra') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('aktif','nonaktif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mata_pelajaran_lembaga_id_kode_unique` (`lembaga_id`,`kode`),
  KEY `mata_pelajaran_lembaga_id_status_index` (`lembaga_id`,`status`),
  CONSTRAINT `mata_pelajaran_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nilai_siswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nilai_siswa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `asesmen_id` bigint unsigned NOT NULL,
  `siswa_id` bigint unsigned NOT NULL,
  `komponen_penilaian_id` bigint unsigned NOT NULL,
  `nilai_angka` tinyint unsigned DEFAULT NULL,
  `predikat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nilai_siswa_unik` (`asesmen_id`,`siswa_id`,`komponen_penilaian_id`),
  KEY `nilai_siswa_siswa_id_foreign` (`siswa_id`),
  KEY `nilai_siswa_komponen_penilaian_id_foreign` (`komponen_penilaian_id`),
  KEY `idx_nilai_lmbg_ases_sisw` (`lembaga_id`,`asesmen_id`,`siswa_id`),
  CONSTRAINT `nilai_siswa_asesmen_id_foreign` FOREIGN KEY (`asesmen_id`) REFERENCES `asesmen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nilai_siswa_komponen_penilaian_id_foreign` FOREIGN KEY (`komponen_penilaian_id`) REFERENCES `komponen_penilaian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nilai_siswa_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nilai_siswa_siswa_id_foreign` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nominal_tagihan_jalur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nominal_tagihan_jalur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jenis_tagihan_id` bigint unsigned NOT NULL,
  `jalur_ppdb_id` bigint unsigned NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nominal_tagihan_jalur_jenis_tagihan_id_jalur_ppdb_id_unique` (`jenis_tagihan_id`,`jalur_ppdb_id`),
  KEY `nominal_tagihan_jalur_jalur_ppdb_id_foreign` (`jalur_ppdb_id`),
  CONSTRAINT `nominal_tagihan_jalur_jalur_ppdb_id_foreign` FOREIGN KEY (`jalur_ppdb_id`) REFERENCES `jalur_ppdb` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nominal_tagihan_jalur_jenis_tagihan_id_foreign` FOREIGN KEY (`jenis_tagihan_id`) REFERENCES `jenis_tagihan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nominal_tagihan_siswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nominal_tagihan_siswa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jenis_tagihan_id` bigint unsigned NOT NULL,
  `siswa_id` bigint unsigned NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nominal_tagihan_siswa_jenis_tagihan_id_siswa_id_unique` (`jenis_tagihan_id`,`siswa_id`),
  KEY `nominal_tagihan_siswa_siswa_id_foreign` (`siswa_id`),
  CONSTRAINT `nominal_tagihan_siswa_jenis_tagihan_id_foreign` FOREIGN KEY (`jenis_tagihan_id`) REFERENCES `jenis_tagihan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nominal_tagihan_siswa_siswa_id_foreign` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `event_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` enum('wa','email','database') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `status` enum('sent','failed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_logs_user_id_event_key_index` (`user_id`,`event_key`),
  CONSTRAINT `notification_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orang_tua`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orang_tua` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint unsigned NOT NULL,
  `pekerjaan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orang_tua_person` (`person_id`),
  CONSTRAINT `orang_tua_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pembayaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pembayaran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tagihan_id` bigint unsigned DEFAULT NULL,
  `cicilan_id` bigint unsigned DEFAULT NULL,
  `wallet_id` bigint unsigned DEFAULT NULL,
  `siswa_id` bigint unsigned DEFAULT NULL,
  `sumber` enum('calon_siswa','admin','orang_tua') COLLATE utf8mb4_unicode_ci NOT NULL,
  `metode` enum('transfer_manual','va_bri','cash','qris','wallet_auto','wallet_saldo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'transfer_manual',
  `amount` decimal(12,2) DEFAULT NULL,
  `is_auto_allocation` tinyint(1) NOT NULL DEFAULT '0',
  `channel_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identifier_method` enum('manual','nfc') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('menunggu_pembayaran','menunggu_verifikasi','lunas','ditolak') COLLATE utf8mb4_unicode_ci NOT NULL,
  `topup_status` enum('none','pending','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `catatan_verifikasi` text COLLATE utf8mb4_unicode_ci,
  `diverifikasi_oleh_user_id` bigint unsigned DEFAULT NULL,
  `diverifikasi_pada` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pembayaran_cicilan_id_foreign` (`cicilan_id`),
  KEY `pembayaran_diverifikasi_oleh_user_id_foreign` (`diverifikasi_oleh_user_id`),
  KEY `idx_pembayaran_status_metode` (`status`,`metode`),
  KEY `idx_pembayaran_tagihan_status` (`tagihan_id`,`status`),
  KEY `pembayaran_wallet_id_index` (`wallet_id`),
  KEY `pembayaran_siswa_id_foreign` (`siswa_id`),
  CONSTRAINT `pembayaran_cicilan_id_foreign` FOREIGN KEY (`cicilan_id`) REFERENCES `cicilan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembayaran_diverifikasi_oleh_user_id_foreign` FOREIGN KEY (`diverifikasi_oleh_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pembayaran_siswa_id_foreign` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pembayaran_tagihan_id_foreign` FOREIGN KEY (`tagihan_id`) REFERENCES `tagihan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pembayaran_tagihan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pembayaran_tagihan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pembayaran_id` bigint unsigned NOT NULL,
  `tagihan_id` bigint unsigned NOT NULL,
  `amount_allocated` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pembayaran_tagihan_pembayaran_id_foreign` (`pembayaran_id`),
  KEY `pembayaran_tagihan_tagihan_id_foreign` (`tagihan_id`),
  CONSTRAINT `pembayaran_tagihan_pembayaran_id_foreign` FOREIGN KEY (`pembayaran_id`) REFERENCES `pembayaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembayaran_tagihan_tagihan_id_foreign` FOREIGN KEY (`tagihan_id`) REFERENCES `tagihan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pendaftaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pendaftaran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `calon_murid_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned NOT NULL,
  `tahun_ajaran_id` bigint unsigned NOT NULL,
  `jalur_ppdb_id` bigint unsigned NOT NULL,
  `gelombang_ppdb_id` bigint unsigned NOT NULL,
  `kode_pendaftaran` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_pendaftaran` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('menunggu_verifikasi','diterima','ditolak','daftar_ulang','aktif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu_verifikasi',
  `catatan_keputusan` text COLLATE utf8mb4_unicode_ci,
  `ditetapkan_oleh_user_id` bigint unsigned DEFAULT NULL,
  `ditetapkan_pada` timestamp NULL DEFAULT NULL,
  `sk_ppdb_id` bigint unsigned DEFAULT NULL,
  `akun_pendaftar_id` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pendaftaran_calon_murid_id_gelombang_ppdb_id_unique` (`calon_murid_id`,`gelombang_ppdb_id`),
  UNIQUE KEY `pendaftaran_lembaga_id_kode_pendaftaran_unique` (`lembaga_id`,`kode_pendaftaran`),
  KEY `pendaftaran_tahun_ajaran_id_foreign` (`tahun_ajaran_id`),
  KEY `pendaftaran_jalur_ppdb_id_foreign` (`jalur_ppdb_id`),
  KEY `pendaftaran_ditetapkan_oleh_user_id_foreign` (`ditetapkan_oleh_user_id`),
  KEY `pendaftaran_lembaga_id_status_index` (`lembaga_id`,`status`),
  KEY `pendaftaran_gelombang_ppdb_id_jalur_ppdb_id_index` (`gelombang_ppdb_id`,`jalur_ppdb_id`),
  KEY `pendaftaran_sk_ppdb_id_index` (`sk_ppdb_id`),
  KEY `pendaftaran_akun_pendaftar_id_index` (`akun_pendaftar_id`),
  CONSTRAINT `pendaftaran_calon_murid_id_foreign` FOREIGN KEY (`calon_murid_id`) REFERENCES `calon_murid` (`id`),
  CONSTRAINT `pendaftaran_ditetapkan_oleh_user_id_foreign` FOREIGN KEY (`ditetapkan_oleh_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pendaftaran_gelombang_ppdb_id_foreign` FOREIGN KEY (`gelombang_ppdb_id`) REFERENCES `gelombang_ppdb` (`id`),
  CONSTRAINT `pendaftaran_jalur_ppdb_id_foreign` FOREIGN KEY (`jalur_ppdb_id`) REFERENCES `jalur_ppdb` (`id`),
  CONSTRAINT `pendaftaran_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`),
  CONSTRAINT `pendaftaran_tahun_ajaran_id_foreign` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengajuan_izin_cuti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengajuan_izin_cuti` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `pegawai_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pegawai_id` bigint unsigned NOT NULL,
  `kategori` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `alasan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pengajuan_izin_cuti_lembaga_id_foreign` (`lembaga_id`),
  KEY `pengajuan_izin_cuti_pegawai_type_pegawai_id_index` (`pegawai_type`,`pegawai_id`),
  KEY `pengajuan_izin_cuti_pegawai_type_pegawai_id_tanggal_mulai_index` (`pegawai_type`,`pegawai_id`,`tanggal_mulai`),
  CONSTRAINT `pengajuan_izin_cuti_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengajuan_pengadaan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengajuan_pengadaan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned DEFAULT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `nomor_pengajuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `judul_pengajuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latar_belakang` text COLLATE utf8mb4_unicode_ci,
  `tingkat_urgensi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'biasa',
  `total_estimasi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `nominal_pencairan` decimal(15,2) DEFAULT NULL,
  `tanggal_pencairan` date DEFAULT NULL,
  `catatan_pencairan` text COLLATE utf8mb4_unicode_ci,
  `bukti_transfer_pencairan_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pengajuan_pengadaan_nomor_pengajuan_unique` (`nomor_pengajuan`),
  KEY `pengajuan_pengadaan_yayasan_id_foreign` (`yayasan_id`),
  KEY `pengajuan_pengadaan_lembaga_id_foreign` (`lembaga_id`),
  KEY `pengajuan_pengadaan_created_by_user_id_foreign` (`created_by_user_id`),
  CONSTRAINT `pengajuan_pengadaan_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pengajuan_pengadaan_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pengajuan_pengadaan_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengajuan_pengadaan_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengajuan_pengadaan_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pengajuan_pengadaan_id` bigint unsigned NOT NULL,
  `kategori_aset_id` bigint unsigned DEFAULT NULL,
  `target_ruangan_id` bigint unsigned DEFAULT NULL,
  `nama_barang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `merk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spesifikasi` text COLLATE utf8mb4_unicode_ci,
  `qty` int unsigned NOT NULL DEFAULT '1',
  `satuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unit',
  `estimasi_harga_satuan` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_estimasi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tipe_pencatatan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unit',
  `foto_referensi_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_item` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `catatan_reviewer` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pengajuan_pengadaan_item_pengajuan_pengadaan_id_foreign` (`pengajuan_pengadaan_id`),
  KEY `pengajuan_pengadaan_item_kategori_aset_id_foreign` (`kategori_aset_id`),
  KEY `pengajuan_pengadaan_item_target_ruangan_id_foreign` (`target_ruangan_id`),
  CONSTRAINT `pengajuan_pengadaan_item_kategori_aset_id_foreign` FOREIGN KEY (`kategori_aset_id`) REFERENCES `kategori_aset` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pengajuan_pengadaan_item_pengajuan_pengadaan_id_foreign` FOREIGN KEY (`pengajuan_pengadaan_id`) REFERENCES `pengajuan_pengadaan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pengajuan_pengadaan_item_target_ruangan_id_foreign` FOREIGN KEY (`target_ruangan_id`) REFERENCES `ruangan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengajuan_rapor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengajuan_rapor` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `kelas_id` bigint unsigned NOT NULL,
  `semester_id` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `diajukan_oleh` bigint unsigned DEFAULT NULL,
  `diajukan_pada` timestamp NULL DEFAULT NULL,
  `diverifikasi_oleh` bigint unsigned DEFAULT NULL,
  `diverifikasi_pada` timestamp NULL DEFAULT NULL,
  `disetujui_oleh` bigint unsigned DEFAULT NULL,
  `disetujui_pada` timestamp NULL DEFAULT NULL,
  `catatan_revisi` text COLLATE utf8mb4_unicode_ci,
  `tanggal_rapor` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pengajuan_rapor_kelas_id_semester_id_unique` (`kelas_id`,`semester_id`),
  KEY `pengajuan_rapor_semester_id_foreign` (`semester_id`),
  KEY `pengajuan_rapor_diajukan_oleh_foreign` (`diajukan_oleh`),
  KEY `pengajuan_rapor_diverifikasi_oleh_foreign` (`diverifikasi_oleh`),
  KEY `pengajuan_rapor_disetujui_oleh_foreign` (`disetujui_oleh`),
  KEY `idx_pengajuan_rapor_status` (`lembaga_id`,`semester_id`,`status`),
  CONSTRAINT `pengajuan_rapor_diajukan_oleh_foreign` FOREIGN KEY (`diajukan_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pengajuan_rapor_disetujui_oleh_foreign` FOREIGN KEY (`disetujui_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pengajuan_rapor_diverifikasi_oleh_foreign` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pengajuan_rapor_kelas_id_foreign` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pengajuan_rapor_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pengajuan_rapor_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `penugasan_shift`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `penugasan_shift` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `pegawai_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pegawai_id` bigint unsigned NOT NULL,
  `jenis_shift_id` bigint unsigned NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `hari_kerja` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `penugasan_shift_lembaga_id_foreign` (`lembaga_id`),
  KEY `penugasan_shift_pegawai_type_pegawai_id_index` (`pegawai_type`,`pegawai_id`),
  KEY `penugasan_shift_jenis_shift_id_foreign` (`jenis_shift_id`),
  KEY `penugasan_shift_pegawai_type_pegawai_id_tanggal_mulai_index` (`pegawai_type`,`pegawai_id`,`tanggal_mulai`),
  CONSTRAINT `penugasan_shift_jenis_shift_id_foreign` FOREIGN KEY (`jenis_shift_id`) REFERENCES `jenis_shift` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penugasan_shift_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `persons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `persons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `nik` text COLLATE utf8mb4_unicode_ci,
  `nik_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_lengkap` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_kelamin` enum('L','P') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tempat_lahir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `agama` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kewarganegaraan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WNI',
  `no_hp` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat_jalan` text COLLATE utf8mb4_unicode_ci,
  `rt` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rw` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `desa_kelurahan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kecamatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kabupaten_kota` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provinsi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kode_pos` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `merged_into_person_id` bigint unsigned DEFAULT NULL,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_persons_yayasan_nik` (`yayasan_id`,`nik_hash`),
  UNIQUE KEY `persons_user_id_unique` (`user_id`),
  KEY `persons_merged_into_person_id_foreign` (`merged_into_person_id`),
  KEY `persons_nama_lengkap_index` (`nama_lengkap`),
  CONSTRAINT `persons_merged_into_person_id_foreign` FOREIGN KEY (`merged_into_person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `persons_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `persons_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pola_jam`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pola_jam` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pola_jam_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `pola_jam_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `presensi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `presensi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sesi_pembelajaran_id` bigint unsigned NOT NULL,
  `siswa_id` bigint unsigned NOT NULL,
  `status` enum('hadir','izin','sakit','alpa','terlambat') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hadir',
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `presensi_sesi_pembelajaran_id_siswa_id_unique` (`sesi_pembelajaran_id`,`siswa_id`),
  KEY `presensi_siswa_id_foreign` (`siswa_id`),
  CONSTRAINT `presensi_sesi_pembelajaran_id_foreign` FOREIGN KEY (`sesi_pembelajaran_id`) REFERENCES `sesi_pembelajaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `presensi_siswa_id_foreign` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `program_inklusi_lembaga`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `program_inklusi_lembaga` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `kebutuhan_khusus` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_sk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_sk` date DEFAULT NULL,
  `tmt` date DEFAULT NULL,
  `tst` date DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `program_inklusi_lembaga_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `program_inklusi_lembaga_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `riwayat_mutasi_aset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `riwayat_mutasi_aset` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aset_barang_id` bigint unsigned NOT NULL,
  `ruangan_asal_id` bigint unsigned NOT NULL,
  `ruangan_tujuan_id` bigint unsigned NOT NULL,
  `qty_pindah` int unsigned NOT NULL DEFAULT '1',
  `tanggal_mutasi` date NOT NULL,
  `alasan_mutasi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `dilakukan_oleh_user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `riwayat_mutasi_aset_ruangan_asal_id_foreign` (`ruangan_asal_id`),
  KEY `riwayat_mutasi_aset_ruangan_tujuan_id_foreign` (`ruangan_tujuan_id`),
  KEY `riwayat_mutasi_aset_dilakukan_oleh_user_id_foreign` (`dilakukan_oleh_user_id`),
  KEY `riwayat_mutasi_aset_aset_barang_id_tanggal_mutasi_index` (`aset_barang_id`,`tanggal_mutasi`),
  CONSTRAINT `riwayat_mutasi_aset_aset_barang_id_foreign` FOREIGN KEY (`aset_barang_id`) REFERENCES `aset_barang` (`id`) ON DELETE CASCADE,
  CONSTRAINT `riwayat_mutasi_aset_dilakukan_oleh_user_id_foreign` FOREIGN KEY (`dilakukan_oleh_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `riwayat_mutasi_aset_ruangan_asal_id_foreign` FOREIGN KEY (`ruangan_asal_id`) REFERENCES `ruangan` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `riwayat_mutasi_aset_ruangan_tujuan_id_foreign` FOREIGN KEY (`ruangan_tujuan_id`) REFERENCES `ruangan` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `riwayat_pendidikan_guru`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `riwayat_pendidikan_guru` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `guru_id` bigint unsigned NOT NULL,
  `jenjang_pendidikan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gelar_akademik` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sekolah_formal` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fakultas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bidang_studi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kependidikan` tinyint(1) NOT NULL DEFAULT '0',
  `tahun_masuk` smallint unsigned DEFAULT NULL,
  `tahun_lulus` smallint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `riwayat_pendidikan_guru_guru_id_foreign` (`guru_id`),
  CONSTRAINT `riwayat_pendidikan_guru_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_level` enum('yayasan','lembaga','diri_sendiri','platform') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lembaga',
  `is_protected` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rpp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rpp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned NOT NULL,
  `guru_id` bigint unsigned NOT NULL,
  `tahun_ajaran_id` bigint unsigned NOT NULL,
  `semester_id` bigint unsigned NOT NULL,
  `kelas_id` bigint unsigned NOT NULL,
  `mata_pelajaran_id` bigint unsigned DEFAULT NULL,
  `judul_topik` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alokasi_waktu` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pertemuan_ke` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size_bytes` bigint unsigned NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `catatan_revisi` text COLLATE utf8mb4_unicode_ci,
  `verified_by_user_id` bigint unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rpp_yayasan_id_foreign` (`yayasan_id`),
  KEY `rpp_tahun_ajaran_id_foreign` (`tahun_ajaran_id`),
  KEY `rpp_semester_id_foreign` (`semester_id`),
  KEY `rpp_mata_pelajaran_id_foreign` (`mata_pelajaran_id`),
  KEY `rpp_verified_by_user_id_foreign` (`verified_by_user_id`),
  KEY `rpp_lembaga_id_status_index` (`lembaga_id`,`status`),
  KEY `rpp_guru_id_status_index` (`guru_id`,`status`),
  KEY `rpp_kelas_id_semester_id_index` (`kelas_id`,`semester_id`),
  CONSTRAINT `rpp_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rpp_kelas_id_foreign` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rpp_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rpp_mata_pelajaran_id_foreign` FOREIGN KEY (`mata_pelajaran_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rpp_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rpp_tahun_ajaran_id_foreign` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rpp_verified_by_user_id_foreign` FOREIGN KEY (`verified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rpp_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ruangan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruangan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `yayasan_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `gedung_id` bigint unsigned NOT NULL,
  `kode_ruangan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_ruangan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lantai` int NOT NULL DEFAULT '1',
  `jenis_ruangan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kelas_teori',
  `kapasitas_siswa` int unsigned DEFAULT NULL,
  `luas_m2` decimal(8,2) DEFAULT NULL,
  `penanggung_jawab_guru_id` bigint unsigned DEFAULT NULL,
  `is_shared` tinyint(1) NOT NULL DEFAULT '0',
  `is_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ruangan_yayasan_id_foreign` (`yayasan_id`),
  KEY `ruangan_penanggung_jawab_guru_id_foreign` (`penanggung_jawab_guru_id`),
  KEY `ruangan_lembaga_id_is_aktif_index` (`lembaga_id`,`is_aktif`),
  KEY `ruangan_gedung_id_lantai_index` (`gedung_id`,`lantai`),
  CONSTRAINT `ruangan_gedung_id_foreign` FOREIGN KEY (`gedung_id`) REFERENCES `gedung` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ruangan_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ruangan_penanggung_jawab_guru_id_foreign` FOREIGN KEY (`penanggung_jawab_guru_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ruangan_yayasan_id_foreign` FOREIGN KEY (`yayasan_id`) REFERENCES `yayasan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seleksi_ppdb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seleksi_ppdb` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jalur_ppdb_id` bigint unsigned NOT NULL,
  `gelombang_ppdb_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned NOT NULL,
  `jenis_tes_master_id` bigint unsigned NOT NULL,
  `jadwal` datetime NOT NULL,
  `kriteria_kelulusan` text COLLATE utf8mb4_unicode_ci,
  `bobot` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `seleksi_ppdb_jalur_ppdb_id_foreign` (`jalur_ppdb_id`),
  KEY `seleksi_ppdb_gelombang_ppdb_id_foreign` (`gelombang_ppdb_id`),
  KEY `seleksi_ppdb_lembaga_id_foreign` (`lembaga_id`),
  KEY `seleksi_ppdb_jenis_tes_master_id_foreign` (`jenis_tes_master_id`),
  CONSTRAINT `seleksi_ppdb_gelombang_ppdb_id_foreign` FOREIGN KEY (`gelombang_ppdb_id`) REFERENCES `gelombang_ppdb` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seleksi_ppdb_jalur_ppdb_id_foreign` FOREIGN KEY (`jalur_ppdb_id`) REFERENCES `jalur_ppdb` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seleksi_ppdb_jenis_tes_master_id_foreign` FOREIGN KEY (`jenis_tes_master_id`) REFERENCES `jenis_tes_master` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `seleksi_ppdb_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `semester`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `semester` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tahun_ajaran_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned NOT NULL,
  `nama` enum('Ganjil','Genap') COLLATE utf8mb4_unicode_ci NOT NULL,
  `urutan` tinyint unsigned NOT NULL,
  `kode_dapodik` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `status_aktif` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `semester_tahun_ajaran_id_nama_unique` (`tahun_ajaran_id`,`nama`),
  KEY `semester_lembaga_id_foreign` (`lembaga_id`),
  CONSTRAINT `semester_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `semester_tahun_ajaran_id_foreign` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sertifikasi_guru`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sertifikasi_guru` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `guru_id` bigint unsigned NOT NULL,
  `jenis_sertifikasi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nomor_sertifikat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bidang_studi_sertifikasi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nrg` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tahun_sertifikasi` smallint unsigned DEFAULT NULL,
  `kode_lembaga_sertifikasi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sertifikasi_guru_guru_id_foreign` (`guru_id`),
  CONSTRAINT `sertifikasi_guru_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sesi_pembelajaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sesi_pembelajaran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `jadwal_pelajaran_id` bigint unsigned DEFAULT NULL,
  `kelas_id` bigint unsigned NOT NULL,
  `guru_id` bigint unsigned NOT NULL,
  `mata_pelajaran_id` bigint unsigned DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `materi` text COLLATE utf8mb4_unicode_ci,
  `status` enum('terlaksana','diganti','kosong') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'terlaksana',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sesi_pembelajaran_jadwal_pelajaran_id_tanggal_unique` (`jadwal_pelajaran_id`,`tanggal`),
  KEY `sesi_pembelajaran_kelas_id_foreign` (`kelas_id`),
  KEY `sesi_pembelajaran_guru_id_foreign` (`guru_id`),
  KEY `sesi_pembelajaran_mata_pelajaran_id_foreign` (`mata_pelajaran_id`),
  KEY `idx_sesi_lembaga_tgl_sts` (`lembaga_id`,`tanggal`,`status`),
  CONSTRAINT `sesi_pembelajaran_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sesi_pembelajaran_jadwal_pelajaran_id_foreign` FOREIGN KEY (`jadwal_pelajaran_id`) REFERENCES `jadwal_pelajaran` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sesi_pembelajaran_kelas_id_foreign` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sesi_pembelajaran_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sesi_pembelajaran_mata_pelajaran_id_foreign` FOREIGN KEY (`mata_pelajaran_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `siswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `siswa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned NOT NULL,
  `kelas_id` bigint unsigned DEFAULT NULL,
  `calon_murid_id` bigint unsigned DEFAULT NULL,
  `pendaftaran_asal_id` bigint unsigned DEFAULT NULL,
  `sumber_data` enum('spmb','import','manual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nisn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('aktif','lulus','pindah','keluar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `siswa_lembaga_id_nis_unique` (`lembaga_id`,`nis`),
  KEY `siswa_kelas_id_foreign` (`kelas_id`),
  KEY `siswa_calon_murid_id_foreign` (`calon_murid_id`),
  KEY `siswa_pendaftaran_asal_id_foreign` (`pendaftaran_asal_id`),
  KEY `idx_siswa_lembaga_status` (`lembaga_id`,`status`),
  KEY `siswa_person_id_foreign` (`person_id`),
  CONSTRAINT `siswa_calon_murid_id_foreign` FOREIGN KEY (`calon_murid_id`) REFERENCES `calon_murid` (`id`) ON DELETE SET NULL,
  CONSTRAINT `siswa_kelas_id_foreign` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `siswa_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE,
  CONSTRAINT `siswa_pendaftaran_asal_id_foreign` FOREIGN KEY (`pendaftaran_asal_id`) REFERENCES `pendaftaran` (`id`) ON DELETE SET NULL,
  CONSTRAINT `siswa_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `siswa_keringanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `siswa_keringanan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `siswa_id` bigint unsigned NOT NULL,
  `kategori_keringanan_id` bigint unsigned NOT NULL,
  `berlaku_dari` date NOT NULL,
  `berlaku_sampai` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `siswa_keringanan_siswa_id_foreign` (`siswa_id`),
  KEY `siswa_keringanan_kategori_keringanan_id_foreign` (`kategori_keringanan_id`),
  CONSTRAINT `siswa_keringanan_kategori_keringanan_id_foreign` FOREIGN KEY (`kategori_keringanan_id`) REFERENCES `kategori_keringanan` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `siswa_keringanan_siswa_id_foreign` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `siswa_orang_tua`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `siswa_orang_tua` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `siswa_id` bigint unsigned NOT NULL,
  `orang_tua_id` bigint unsigned NOT NULL,
  `hubungan` enum('ayah','ibu','wali') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_kontak_utama` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `siswa_orang_tua_siswa_id_orang_tua_id_unique` (`siswa_id`,`orang_tua_id`),
  KEY `siswa_orang_tua_orang_tua_id_foreign` (`orang_tua_id`),
  CONSTRAINT `siswa_orang_tua_orang_tua_id_foreign` FOREIGN KEY (`orang_tua_id`) REFERENCES `orang_tua` (`id`) ON DELETE CASCADE,
  CONSTRAINT `siswa_orang_tua_siswa_id_foreign` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sk_ppdb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sk_ppdb` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gelombang_ppdb_id` bigint unsigned NOT NULL,
  `lembaga_id` bigint unsigned NOT NULL,
  `nomor_sk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_terbit` date NOT NULL,
  `diterbitkan_oleh_user_id` bigint unsigned NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sk_ppdb_lembaga_id_nomor_sk_unique` (`lembaga_id`,`nomor_sk`),
  KEY `sk_ppdb_gelombang_ppdb_id_foreign` (`gelombang_ppdb_id`),
  KEY `sk_ppdb_diterbitkan_oleh_user_id_foreign` (`diterbitkan_oleh_user_id`),
  CONSTRAINT `sk_ppdb_diterbitkan_oleh_user_id_foreign` FOREIGN KEY (`diterbitkan_oleh_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `sk_ppdb_gelombang_ppdb_id_foreign` FOREIGN KEY (`gelombang_ppdb_id`) REFERENCES `gelombang_ppdb` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sk_ppdb_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `skema_cicilan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `skema_cicilan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tagihan_id` bigint unsigned NOT NULL,
  `jumlah_termin` tinyint unsigned NOT NULL,
  `dibuat_oleh` enum('calon_siswa','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `dibuat_oleh_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `skema_cicilan_tagihan_id_unique` (`tagihan_id`),
  KEY `skema_cicilan_dibuat_oleh_user_id_foreign` (`dibuat_oleh_user_id`),
  CONSTRAINT `skema_cicilan_dibuat_oleh_user_id_foreign` FOREIGN KEY (`dibuat_oleh_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `skema_cicilan_tagihan_id_foreign` FOREIGN KEY (`tagihan_id`) REFERENCES `tagihan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_settings_lembaga_id_key_unique` (`lembaga_id`,`key`),
  KEY `system_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `system_settings_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE SET NULL,
  CONSTRAINT `system_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tagihan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tagihan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pendaftaran_id` bigint unsigned DEFAULT NULL,
  `tagihable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tagihable_id` bigint unsigned DEFAULT NULL,
  `jenis_tagihan_id` bigint unsigned DEFAULT NULL,
  `kategori` enum('pendaftaran','daftar_ulang','spp','tahunan','kegiatan','custom','lainnya') COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_period` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_trigger` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `total_tagihan` decimal(12,2) NOT NULL,
  `discount_amount` decimal(12,2) DEFAULT NULL,
  `discount_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `net_amount` decimal(12,2) DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('belum_bayar','dicicil','lunas','sebagian','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_bayar',
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `jatuh_tempo` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tagihan_pendaftaran_id_kategori_unique` (`pendaftaran_id`,`kategori`),
  KEY `idx_tagihan_status_jtempo` (`status`,`jatuh_tempo`),
  KEY `tagihan_jenis_tagihan_id_foreign` (`jenis_tagihan_id`),
  KEY `tagihan_cancelled_by_foreign` (`cancelled_by`),
  KEY `tagihan_tagihable_type_tagihable_id_index` (`tagihable_type`,`tagihable_id`),
  CONSTRAINT `tagihan_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tagihan_jenis_tagihan_id_foreign` FOREIGN KEY (`jenis_tagihan_id`) REFERENCES `jenis_tagihan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tagihan_pendaftaran_id_foreign` FOREIGN KEY (`pendaftaran_id`) REFERENCES `pendaftaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tagihan_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tagihan_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tagihan_id` bigint unsigned NOT NULL,
  `jenis_tagihan_id` bigint unsigned NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tagihan_item_tagihan_id_foreign` (`tagihan_id`),
  KEY `tagihan_item_jenis_tagihan_id_foreign` (`jenis_tagihan_id`),
  CONSTRAINT `tagihan_item_jenis_tagihan_id_foreign` FOREIGN KEY (`jenis_tagihan_id`) REFERENCES `jenis_tagihan` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tagihan_item_tagihan_id_foreign` FOREIGN KEY (`tagihan_id`) REFERENCES `tagihan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tahun_ajaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tahun_ajaran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lembaga_id` bigint unsigned NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `status_aktif` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tahun_ajaran_lembaga_id_nama_unique` (`lembaga_id`,`nama`),
  CONSTRAINT `tahun_ajaran_lembaga_id_foreign` FOREIGN KEY (`lembaga_id`) REFERENCES `lembaga` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_notification_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'finance',
  `channel_push` tinyint(1) NOT NULL DEFAULT '0',
  `channel_wa` tinyint(1) NOT NULL DEFAULT '1',
  `channel_email` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_notification_preferences_user_id_module_unique` (`user_id`,`module`),
  CONSTRAINT `user_notification_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `merged_into_user_id` bigint unsigned DEFAULT NULL,
  `lembaga_id` bigint unsigned DEFAULT NULL,
  `yayasan_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_lembaga_id_is_active_index` (`lembaga_id`,`is_active`),
  KEY `users_lembaga_id_index` (`lembaga_id`),
  KEY `users_yayasan_id_index` (`yayasan_id`),
  KEY `users_merged_into_user_id_foreign` (`merged_into_user_id`),
  CONSTRAINT `users_merged_into_user_id_foreign` FOREIGN KEY (`merged_into_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verifikasi_email_otp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verifikasi_email_otp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode_otp` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `verifikasi_email_otp_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallet_mutasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_mutasi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wallet_id` bigint unsigned NOT NULL,
  `pembayaran_id` bigint unsigned DEFAULT NULL,
  `tipe` enum('topup','debit','refund') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `saldo_sebelum` decimal(15,2) NOT NULL,
  `saldo_sesudah` decimal(15,2) NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wallet_mutasi_wallet_id_foreign` (`wallet_id`),
  KEY `wallet_mutasi_pembayaran_id_foreign` (`pembayaran_id`),
  CONSTRAINT `wallet_mutasi_pembayaran_id_foreign` FOREIGN KEY (`pembayaran_id`) REFERENCES `pembayaran` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_mutasi_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `siswa_id` bigint unsigned NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `va_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_topup` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_deducted` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallets_siswa_id_unique` (`siswa_id`),
  UNIQUE KEY `wallets_va_number_unique` (`va_number`),
  CONSTRAINT `wallets_siswa_id_foreign` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_template` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isi_template` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_template_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `workflow_definitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflow_definitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_workflow` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_definitions_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `workflow_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflow_steps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow_definition_id` bigint unsigned NOT NULL,
  `step_number` int unsigned NOT NULL,
  `step_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approver_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approver_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_level` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lembaga',
  `is_final_step` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_steps_workflow_definition_id_step_number_unique` (`workflow_definition_id`,`step_number`),
  CONSTRAINT `workflow_steps_workflow_definition_id_foreign` FOREIGN KEY (`workflow_definition_id`) REFERENCES `workflow_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `yayasan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `yayasan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `npwp_yayasan` text COLLATE utf8mb4_unicode_ci,
  `akta_pendirian_nomor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `akta_pendirian_tanggal` date DEFAULT NULL,
  `sk_kemenkumham_nomor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `telepon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_ketua_pembina` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_ketua_pengurus` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_07_12_073217_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_07_12_073239_create_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_07_12_090129_create_yayasan_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_07_12_090702_create_lembaga_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_07_12_094759_create_layanan_khusus_lembaga_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_07_12_094800_create_ekstrakurikuler_lembaga_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_07_12_094800_create_program_inklusi_lembaga_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_07_12_095318_create_guru_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_07_12_095746_create_riwayat_pendidikan_guru_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_07_12_095753_create_sertifikasi_guru_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_07_12_100246_create_jabatan_tambahan_master_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_07_12_100251_create_guru_jabatan_tambahan_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_07_12_100820_create_tahun_ajaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_07_12_101316_create_semester_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_07_12_102600_create_lembaga_data_periodik_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_07_13_090000_create_jenis_tes_master_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_07_13_090100_create_gelombang_ppdb_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_07_13_090200_create_jalur_ppdb_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_07_13_090300_create_formulir_field_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_07_13_090400_create_dokumen_syarat_ppdb_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_07_13_090500_create_seleksi_ppdb_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_07_14_090000_create_calon_murid_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_07_14_090100_create_alamat_calon_murid_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_07_14_090200_create_keluarga_calon_murid_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_07_14_090300_create_data_periodik_calon_murid_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_07_14_090400_create_data_khusus_calon_murid_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_07_14_090500_create_pendaftaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_07_14_090600_create_jawaban_formulir_pendaftaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_07_14_090700_create_dokumen_pendaftaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_07_14_090800_create_verifikasi_email_otp_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_07_14_091100_create_hasil_seleksi_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_07_14_091200_create_sk_ppdb_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_07_15_100000_create_akun_pendaftar_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_07_15_100200_create_akun_pendaftar_password_reset_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_07_15_120000_create_jenis_tagihan_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_07_15_120100_create_nominal_tagihan_jalur_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_07_15_120200_create_tagihan_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_07_15_120300_create_tagihan_item_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_07_16_100000_create_skema_cicilan_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_07_16_100100_create_cicilan_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_07_16_100200_create_pembayaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_07_18_100000_create_gelombang_jalur_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_07_25_090000_create_mata_pelajaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_07_25_090100_create_kelas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_07_25_090200_create_siswa_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_07_25_100100_create_kalender_akademik_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_07_25_110000_create_pola_jam_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_07_25_110100_create_jam_pelajaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_07_25_110300_create_jadwal_pelajaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_07_25_120000_create_sesi_pembelajaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_07_25_120100_create_presensi_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_07_25_130000_create_komponen_penilaian_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_07_25_131000_create_asesmen_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_07_25_140000_create_orang_tua_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_07_25_140100_create_siswa_orang_tua_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_08_04_150000_create_jenis_karyawan_master_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_08_04_150100_create_karyawan_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_08_04_160000_create_kasus_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_08_04_160100_create_kasus_consent_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_08_04_160200_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_08_04_170000_create_kasus_sesi_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_08_04_170100_create_kasus_tugas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_08_04_170200_create_kasus_tugas_submission_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_08_05_100000_create_kasus_evaluasi_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_08_05_110000_widen_kasus_status_enum',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_08_06_090000_add_soft_deletes_to_kasus_family',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_08_06_100000_create_whatsapp_template_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_08_08_090000_add_batch_columns_to_kasus_tugas',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_08_10_090000_create_jenis_tagihan_sasaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_08_10_100000_create_nominal_tagihan_siswa_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_08_10_110000_create_keringanan_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_08_10_120000_create_pembayaran_tagihan_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_08_10_130000_add_polymorphic_columns_to_tagihan_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_08_10_140000_backfill_tagihan_tagihable_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_08_10_150000_add_billing_columns_to_jenis_tagihan_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_08_10_160000_add_wallet_columns_to_pembayaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_08_10_180000_drop_iuran_columns_from_lembaga_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_08_11_090000_create_billing_job_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_08_11_100000_add_lainnya_to_tagihan_kategori_enum',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_08_11_101041_create_system_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2026_08_11_101041_create_wallets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2026_08_11_101042_create_wallet_mutasis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2026_08_11_200000_add_siswa_id_and_status_to_pembayaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2026_08_11_200001_create_bri_virtual_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2026_08_11_200002_create_bri_qris_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2026_08_11_200003_create_manual_payment_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2026_08_11_210000_add_amount_to_pembayaran_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2026_08_12_090000_create_user_notification_preferences_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2026_08_12_090001_create_notification_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2026_08_14_024409_add_reference_no_to_bri_qris_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2026_08_15_100000_create_bri_inbound_payment_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2026_08_16_120000_create_gedung_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2026_08_16_120100_create_ruangan_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2026_08_16_120200_add_ruangan_id_to_kelas_and_jadwal_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2026_08_16_120300_create_kategori_aset_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2026_08_16_120400_create_aset_barang_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2026_08_16_120500_create_riwayat_mutasi_aset_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2026_08_16_130000_create_universal_workflow_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2026_08_16_130100_create_pengadaan_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2026_08_17_124500_create_rpp_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2026_08_17_231529_add_yayasan_id_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2026_08_19_160000_create_pengajuan_rapor_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2026_08_19_160100_create_catatan_wali_kelas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2026_08_19_160200_add_kktp_minimal_to_komponen_penilaian_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2026_08_19_190000_add_elemen_cp_to_komponen_penilaian_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2026_08_21_000001_cleanup_legacy_permissions_and_sync_pivot',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2026_08_22_090000_create_attendance_method_configurations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2026_08_22_090100_create_attendance_points_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2026_08_22_090200_create_attendance_events_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2026_08_22_090300_create_attendance_records_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2026_08_22_090400_create_employee_qr_codes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2026_08_22_100000_add_hari_libur_mingguan_sdm_to_lembaga_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2026_08_22_100100_create_kalender_kerja_sdm_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2026_08_22_110000_create_attendance_policies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2026_08_22_110100_add_is_late_columns_to_attendance_records_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2026_08_22_120000_create_jenis_shift_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2026_08_22_120100_create_penugasan_shift_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2026_08_22_130000_create_pengajuan_izin_cuti_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2026_08_23_100000_create_kuota_cuti_config_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2026_08_24_140000_add_platform_scope_level_to_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2026_08_26_100000_create_elemen_cp_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2026_08_26_100100_add_subjek_columns_to_komponen_penilaian_and_asesmen',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2026_08_26_100200_backfill_subjek_penilaian',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2026_08_26_100300_drop_mata_pelajaran_id_from_komponen_penilaian_and_asesmen',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2026_08_26_110000_add_assessment_type_to_komponen_penilaian_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2026_08_27_090000_create_fase_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2026_08_27_090100_create_fase_default_mapping_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2026_08_27_090200_add_fase_id_to_kelas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2026_08_27_100000_remove_aspek_perkembangan_from_mata_pelajaran_tipe',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2026_08_27_110000_create_kurikulum_assignment_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2026_08_27_110100_add_kurikulum_to_kelas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2026_08_29_000001_create_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2026_08_29_000002_add_person_id_and_relax_identity_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2026_08_29_000099_make_person_id_not_null_and_add_fk',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2026_09_01_000001_drop_legacy_identity_columns_from_role_tables',1);
