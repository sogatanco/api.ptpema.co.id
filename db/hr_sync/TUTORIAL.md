# Tutorial: sinkronisasi data karyawan apam ke HR (dengan tes dan rollback tiap langkah)

Efek lengkap: `DAMPAK_PERUBAHAN.md`. Skrip sudah diuji di salinan lokal (kecuali 27 dan 29: hanya divalidasi dengan SELECT di server), BELUM dijalankan di live.
Perintah: `mysql -u <user> -p < berkas.sql` (khusus 17 dan 18 tambahkan `--comments`). Aturan data: bila apam dan HR berbeda, HR yang dipakai.
Aturan: kerjakan berurutan; bila TES sebuah langkah tidak sesuai, jalankan ROLLBACK langkah itu (dan langkah sebelumnya, dari belakang), berhenti, lalu periksa.

| Urutan | Berkas | Isi | Rollback |
|---|---|---|---|
| 1 | `19_hr_add_sekkom.sql` | HR: jabatan Sekretaris Komisaris (id 68) untuk SEKKOM-01 (Saifan) | `20` |
| 2 | `13_views_live_apply.sql` | view `struktur_lengkap_oke`, `divisions`, `atasan_terkait` membaca HR | `14` |
| 3 | `17_triggers_apply.sql` | id jabatan tetap di 5 trigger disesuaikan ke id HR | `18` |
| 4 | `21_employees_hr_view.sql` | view `employees_hr` + 5 view dialihkan | `22` |
| 5 | deploy kode branch `synch` | kode membaca HR | `git checkout main` |
| 6 | `27_employees_sync_from_hr.sql` | nama + status aktif `employees` mengikuti HR (43 karyawan) | `28` |
| 7 | `29_project_pic_sync_from_hr.sql` | PIC project (147) yang nonaktif di HR diganti | `30` |
| opsional | `23_hr_public_keys_apply.sql` | public key e-sign dipusatkan di HR (perlu persetujuan HR) | `24` |
| nanti | `25_apam_cleanup_apply.sql` | tabel apam tak terpakai diganti nama `zz_hapus_*` | `26` |

## Langkah 0. Persiapan
- Backup: `mysqldump --single-transaction --routines --triggers --events` untuk `ptpemaco_apam`, `ptpemaco_admnew`, `ptpemaco_sppdnew`, `ptpemaco_esign`, `ptpemaco_hr_production`; kompres, uji `gzip -t`, simpan juga di luar server DB.
- Siapkan berkas rollback 14, 18, 20, 22, 28, 30 (dan 24 bila 23 dipakai) di server. Pilih jam sepi (sekitar 30-45 menit untuk semuanya).
- Catat baseline:
```sql
SELECT COUNT(*) AS baris FROM ptpemaco_apam.struktur_lengkap_oke;
SELECT COUNT(*) AS total, SUM(live_receiver IS NULL) AS kosong FROM ptpemaco_admnew.list_surat_masuk;
SELECT COUNT(*) AS total, SUM(live_cc IS NULL) AS kosong FROM ptpemaco_admnew.list_cc;
SELECT COUNT(*) FROM ptpemaco_apam.pic_task; SELECT COUNT(*) FROM ptpemaco_apam.task_latest_status; SELECT COUNT(*) FROM ptpemaco_apam.list_task_final;
```
- TES: semuanya jalan tanpa error dan angkanya tercatat.

## Langkah 1. SEKKOM-01 di HR (atas persetujuan HR; boleh jauh sebelum hari H)
Jalankan `19_hr_add_sekkom.sql` (hanya menambah jabatan id 68; nama dan email tidak diubah; email HR dan akun apam harus sama: `saipan@pema.co.id`).
- TES: laporan 1 baris: `SEKKOM-01`, `Saifan Saputra`, `position_id` 68. Ulang skrip tidak menambah baris kedua.
- ROLLBACK: `20_hr_add_sekkom_rollback.sql`; TES: jumlah_jabatan 0.

## Langkah 2. Maintenance ON
`php artisan down`. Pastikan tidak ada penulisan baru ke surat masuk, disposisi, SPPD.

## Langkah 3. Pasang view (13)
Jalankan `13_views_live_apply.sql`.
- TES:
```sql
SELECT table_name FROM information_schema.views WHERE table_schema='ptpemaco_apam' AND table_name LIKE '%\_backup';   -- 3 baris
SELECT COUNT(*) FROM ptpemaco_apam.struktur_lengkap_oke;                  -- = karyawan aktif berjabatan di HR (query di bawah)
SELECT COUNT(DISTINCT u.employe_id) FROM ptpemaco_hr_production.users u JOIN ptpemaco_hr_production.employee_positions p ON p.employe_id=u.employe_id WHERE u.active=1;
SELECT employe_id, COUNT(*) FROM ptpemaco_apam.struktur_lengkap_oke GROUP BY employe_id HAVING COUNT(*)>1;   -- 0 baris
SELECT position_id, position_name, employe_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id IN (1,3,6,13,43,51,68);  -- 7 baris, ada pemegangnya
SELECT COUNT(*) FROM ptpemaco_apam.task_latest_status; SELECT COUNT(*) FROM ptpemaco_apam.atasan_terkait_oke; SELECT COUNT(*) FROM ptpemaco_apam.divisions;  -- tanpa error
SELECT COUNT(*) AS total, SUM(live_receiver IS NULL) AS kosong FROM ptpemaco_admnew.list_surat_masuk;   -- kosong tidak naik dari baseline
```
- ROLLBACK: `14_views_live_rollback.sql`; TES: jumlah baris struktur kembali ke baseline.

## Langkah 4. Pasang trigger (17), segera setelah 13
Jalankan `mysql --comments -u <user> -p < 17_triggers_apply.sql`. Skrip berhenti sendiri (tanpa mengubah apa pun) bila definisi trigger di server sudah berbeda dari yang dipakai skrip atau id jabatan HR tidak sesuai.
- TES 1: laporan akhir 5 baris, kolom `id_lama_tersisa` = 0 semua.
- TES 2: `SELECT trigger_name, instr(action_statement,'position_id = 68')>0 FROM information_schema.triggers WHERE trigger_name='add_step_verif_1';` = 1.
- TES 3 (aman, di-rollback): buat surat uji di transaksi lalu cek langkah approval:
```sql
START TRANSACTION;
INSERT INTO ptpemaco_admnew.surats(nomor_surat,no_document,kepada,perihal,isi_surat,id_divisi,submitted_by,submitted_current_position,sign_by,created_at)
VALUES('T1','TESTDOC1','x','x','x',20,'202208044K','x','003',NOW());
SELECT step,type,id_employe,id_current_position FROM ptpemaco_esign.verif_steps WHERE id_doc='TESTDOC1' ORDER BY step;  -- ada paraf Sekretaris Perusahaan
ROLLBACK;
```
- ROLLBACK: `mysql --comments -u <user> -p < 18_triggers_rollback.sql`; TES: kolom `sama_dengan_backup` = 1 semua.

## Langkah 5. View karyawan `employees_hr` (21)
Membuat view `employees_hr` (kolom sama dengan `employees`; nama, jabatan id HR, status aktif, data pribadi dari HR; `public_key` dan `user_id` dari apam) dan mengalihkan 5 view (`task_latest_status`, `pic_task`, `list_detail_pic_task`, `send_message`, `list_task_final`). Tidak menulis data. WAJIB terpasang SEBELUM kode `synch` di-deploy.
Jalankan `mysql --comments -u <user> -p < 21_employees_hr_view.sql`.
- TES:
```sql
SELECT (SELECT COUNT(*) FROM ptpemaco_apam.employees_hr) AS employees_hr, (SELECT COUNT(*) FROM ptpemaco_hr_production.users) AS users_hr;  -- employees_hr = users_hr + karyawan apam yang tidak ada di HR (2)
SELECT employe_id, first_name, position_id, employe_active FROM ptpemaco_apam.employees_hr WHERE employe_id IN ('003','SEKKOM-01');   -- nama dan id jabatan HR
SELECT COUNT(*) FROM ptpemaco_apam.pic_task; SELECT COUNT(*) FROM ptpemaco_apam.task_latest_status; SELECT COUNT(*) FROM ptpemaco_apam.list_task_final;   -- sama dengan baseline; hanya nama yang berubah mengikuti HR
```
- ROLLBACK: kembalikan kode lama dulu bila kode baru sudah di-deploy, lalu `22_employees_hr_rollback.sql`; TES: laporan `view_lama_kembali` = 5.

## Langkah 6. Deploy kode (branch `synch`) lalu maintenance OFF
`git checkout synch` di server (atau merge), `php artisan optimize:clear`, `php artisan up`.
- TES (akun uji): login; halaman Project (PIC aktif, anggota, riwayat), Daily, Task (PIC, komentar), Tiket, Notifikasi menampilkan nama/jabatan sesuai HR; Daftar Surat Masuk terbuka dan nama penerima terisi; Tambah Surat Masuk (daftar direksi muncul); disposisi + cc berhasil dengan notifikasi; pengajuan SPPD menampilkan tarif sesuai jabatan asli.
- ROLLBACK kode: `git checkout main` lalu `php artisan optimize:clear`.

## Langkah 7. Sinkron data lama (HR yang dipakai bila berbeda); setelah langkah 6 stabil
Semua skrip membuat tabel backup sendiri dan berhenti bila dijalankan dua kali.
- **7A. Karyawan (27)**: `first_name` = nama lengkap HR, `last_name` dikosongkan, `employe_active` mengikuti HR untuk 43 karyawan (9 nama, 38 status). Karyawan yang tidak ada di HR (`202605052K`, `3`) tidak disentuh. Dampak: 38 akun yang nonaktif di HR tidak bisa login.
  `mysql -u <user> -p < 27_employees_sync_from_hr.sql` -> TES: `diubah` = 43 dan `sisa_beda` = 0. ROLLBACK: `28_employees_sync_rollback.sql`.
- **7B. PIC project (29)**: 147 project belum selesai yang PIC aktifnya nonaktif di HR: 126 diganti pengganti menurut HR (PS/PIC aktif, lalu Manajer, lalu Supervisor divisi), 21 project (Manajemen Investasi, SPI) dengan PUK dari view `divisions` (butuh 13). Daftar pasangan tertanam di skrip.
  `mysql -u <user> -p < 29_project_pic_sync_from_hr.sql` -> TES: `diganti` = `di_daftar` - `dilewati`; `sisa_pic_nonaktif` = 0; buka Project -> PIC aktif tampil nama pengganti. ROLLBACK: `30_project_pic_sync_rollback.sql` (aman sebelum PIC baru dipakai review/handover).
- Tidak diubah (keputusan): task belum selesai (1.231) dan tiket (8) dengan PIC nonaktif tetap sebagai riwayat; daily tidak ada yang perlu disesuaikan.

## Langkah 8. (opsional, atas persetujuan HR) Public key e-sign di HR (23)
`public_key` (RSA, dipakai login/e-sign) hanya ada di `apam.employees`. 23 membuat tabel BARU `ptpemaco_hr_production.employee_public_keys`, menyalin key yang ada, dan membuat `employees_hr.public_key` membaca HR (cadangan: `employees`). Private key TIDAK pindah (berkas terenkripsi di `storage/app/keys`). Prasyarat 21.
`mysql --comments -u <user> -p < 23_hr_public_keys_apply.sql`
- TES: `tersalin` = `key_di_apam` - `tidak_ikut`, `terbaca_lewat_view` = `tersalin`; login karyawan yang punya key berhasil dan e-sign dokumen uji berhasil.
- ROLLBACK: `24_hr_public_keys_rollback.sql`; TES: `tabel_hr_tersisa` = 0.

## Rollback total (urutan terbalik)
Maintenance ON -> `30` -> `28` -> kembalikan kode (`git checkout main`) -> `22` -> `18` (`--comments`) -> `14` -> maintenance OFF -> cek baseline langkah 0. Data HR (langkah 1) dibatalkan terpisah dengan 20; 24 bila 23 dijalankan. Cadangan terakhir: pulihkan dump langkah 0.

## Setelah stabil (1-2 minggu): bersihkan
1. Hapus cadangan (setelah ini 14, 18, 22, 28, 30 tidak bisa dipakai lagi; arsipkan dump langkah 0):
```sql
DROP VIEW ptpemaco_apam.atasan_terkait_backup, ptpemaco_apam.divisions_backup, ptpemaco_apam.struktur_lengkap_oke_backup;
DROP TABLE ptpemaco_sppdnew.bak_trigger_defs;
DROP TABLE ptpemaco_apam.employees_sync_bak, ptpemaco_apam.project_pic_sync_bak;
```
2. Objek apam tak terpakai: `25_apam_cleanup_apply.sql` mengganti namanya menjadi `zz_hapus_*` (data utuh; rollback `26`): tabel `nota_dinas` (digantikan `pic` di HR), `struktur` (cerminan jabatan lama), `personal_access_tokens`, `task_approvals`, view `gsdgsd`, `kpi_divisi2`. Setelah 1-2 minggu lagi tanpa masalah, hapus permanen (dump dulu): lihat baris DROP di akhir 25.
3. **Tetap dipakai, jangan dihapus**: `employees` (FK 16 kolom, menyimpan `user_id` dan `public_key`), `users`, `base_positions` (tarif SPPD), `project_stages`, `organizations` (`is_division = 1`, FK projects), `app_clients`, `positions` dan `board_organizations` (masih dirujuk FK dan trigger; kandidat tahap berikut).
