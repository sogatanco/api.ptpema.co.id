# Dampak pemasangan data HR ke apam (saat dipasang di production)

Berlaku untuk paket: `13` (view), `17` (trigger), kode backend, serta `19`, `21`, `27`, `29` (dan `23`, `25` opsional). Langkah pasang: `TUTORIAL.md`.
Ada dua sumber angka. **Server** = diperiksa langsung (hanya baca) pada 2 Nov 2026. **Lokal** = salinan backup `backup_db_2026-10-28_000102` (instance uji port 3391), BUKAN production; pakai sebagai gambaran dan ulangi pemeriksaan di bagian 6 sebelum memasang.

## 1. Yang berubah
| Objek | Perubahan | Menulis data? |
|---|---|---|
| `ptpemaco_apam.struktur_lengkap_oke`, `divisions`, `atasan_terkait` | 13: definisi diganti; jabatan, divisi, board, atasan 1-5, aktif/nonaktif, PIC/PS (pengganti nota dinas), nama, data pribadi, id_base dibaca dari HR. Dari apam hanya `users` (user_id, email, roles), `base_positions` (tarif SPPD), `project_stages`, `organizations` (`is_division = 1`) | Tidak (hanya definisi view) |
| `struktur_lengkap_oke_backup`, `divisions_backup`, `atasan_terkait_backup` | 3 view baru: salinan definisi sebelum diganti | Tidak |
| 5 trigger: `sppdnew.sppds` (`add_step_verif_1`, `after_update1`), `sppdnew.realisasi` (`update`), `admnew.surats` (`add_verif_doc_log2`, `after_update2`) | 17: id jabatan tetap (hardcode) disesuaikan ke id HR (bagian 4). Definisi lama disimpan di `ptpemaco_sppdnew.bak_trigger_defs` | Ya (definisi trigger + 1 tabel backup) |
| Kode backend | `StaticAdmController::getDirektur` dan `SuratMasuk` (nama jabatan penerima) membaca jabatan HR lewat model baru `App\Models\Hr\Position` | - |

Tidak berubah: seluruh tabel HR (tidak ada skrip yang menulis ke HR kecuali 23 bila dipakai), password, `roles`, `user_id`, foto, telepon.
View turunan (`task_latest_status`, `atasan_terkait_oke`, `divisions_oke`, `struktur_lengkap_oke_oke`) otomatis mengikuti; nama dan kolomnya sama. Trigger `pengajuan` dan frontend tidak diubah.

## 2. Efek pada `struktur_lengkap_oke`
Server: live 114 baris; karyawan aktif berjabatan di HR 94 . 27 baris live akan hilang (nonaktif, tanpa jabatan, atau tidak ada di HR), termasuk SEKKOM-01 bila di HR ia belum punya jabatan. Rincian lokal (116 baris -> 99):
- **27 karyawan hilang** 26 nonaktif di HR dan 1 tidak ada di HR (`202605052K`). Di lokal tidak ada tiket atas nama mereka dan tidak ada yang masih menjadi atasan orang lain. Cocokkan dengan daftar di server (bagian 6).
- **SEKKOM-01 (Saifan/Saipan Saputra)**: SEKKOM-01 sudah ada di HR dengan nama Saifan Saputra (tidak ada skrip tambahan). Ia hanya tampil di view bila punya jabatan di HR (HR yang mengisi); bila sudah, ia tampil sebagai Sekretaris Komisaris, terhubung ke akun apam 105 (peran AdminSPPD). Dampak: `id_base` jadi 9 (Staf; di apam lama 2/Komisaris) dan tidak punya atasan (jabatan 68 tanpa `parent_id`). Bila approval bergantung pada `id_base` 2, periksa.
- **11 baris baru**: 9 karyawan nyata (`202601129K`, `202601130K`, `202601131K`, `202602136K`, `202602137K`, `202602138K`, `5`, `6`, `KOM-03`) yang sebelumnya tidak muncul, ditambah 2 akun uji lokal (`T9001`, `T9002`) yang tidak ada di production.
- **Karyawan yang berubah** (dari 88 yang ada di keduanya): nama 8 (mengikuti HR, umumnya lebih lengkap, mis. `Febrina` -> `Febrina Azzahra`), jabatan 14, divisi 9, board 3, atasan langsung 20, `as_pic` 1.
- **id_base / tarif SPPD berubah untuk 4 orang**: tiga staf menjadi Supervisor di HR (`201809007`, `202011019`, `202208061K`; tarif 4 -> 3) dan satu Satpam (`202601128K`; tarif 4 -> 5). Tarif dan nomor SPPD mereka ikut berubah.
- **PS/PIC**: `202402093K` memegang dua jabatan (sendiri + PS Manajer Agro dan Jasa Lainnya sampai 28 Des 2026); ia menjadi atasan bagi bawahan kedua jabatan itu. Teks jabatannya: `Manajer Penelitian dan Pengembangan / PS Manajer Agro dan Jasa Lainnya`.
- **Tarif SPPD tidak ikut PS/PIC**: `base_name`, `rate_sppd`, `nomor_sppd`, dan `id_base` mengikuti jabatan asli. Hanya `real_base` (PUK divisi, `atasan_terkait`) yang ikut PS/PIC.
- **Tidak berubah**: `user_id`, email, `roles`, foto, telepon (0 perbedaan). `nota_dinas` apam tidak lagi dibaca; penggantinya `pic` di HR (aktif menurut tanggal).

## 3. Efek pada `divisions` dan `atasan_terkait`
- `divisions` tetap 15 baris. Berubah 5: Agro dan Jasa Lainnya (PUK `202304068K` -> `202402093K`), SDM (PUK `202304066K` -> `202207055K`), Sekretariat Perusahaan (dir `002` -> `003`), SPI (PUK `202208058K` -> `202311083K`, dir `002` -> `003`), Manajemen Resiko (dir `002` -> `003`). `jumlah` (project) sama. Di server, selisih PUK lama vs baru yang terlihat: Divisi Manajemen Investasi (`202104033` -> `202310079K`) dan SPI (`202208058K` -> `202311083K`).
- `atasan_terkait`: 17 karyawan berubah supervisor/manajer/direktur terkaitnya; jumlah baris ikut `struktur_lengkap_oke`. PS/PIC dihitung sebagai manajer/supervisor menurut jabatan yang dijabat (`real_base`).

## 4. Efek pada trigger (sppdnew, admnew, esign, pengajuan)
Setelah 13, `struktur_lengkap_oke.position_id` = id jabatan HR. Trigger yang mencari orang lewat `position_id` tetap bisa kehilangan langkah approval/notifikasi TANPA error (memakai `LIMIT 1` + `ROW_COUNT()`). Diperiksa di server:

| Id di kode | Jabatan apam | Id yang sama di HR | Id benar di HR | Trigger | Dampak bila tidak diubah |
|---|---|---|---|---|---|
| 1, 3, 6, 13 | Komisaris Utama, Direktur Utama, Dir. Umum & Keuangan, Manajer Keuangan | sama | sama | sppds, realisasi, pengajuan | tidak ada |
| 109 | Sekretaris Komisaris | tidak ada | 68 | `sppds.add_step_verif_1`, `after_update1` | paraf Sekretaris Komisaris pada SPPD Komisaris hilang |
| 88 | Sekretaris Perusahaan | tidak ada | 51 | `admnew.surats.add_verif_doc_log2`, `after_update2` | paraf Sekretaris Perusahaan pada surat keluar hilang |
| 80 | Supervisor Umum | Supervisor Manajemen Resiko | 43 | `sppdnew.realisasi.update` | notifikasi/verifikasi realisasi salah penerima |
| 79 | Manajer Umum (kosong) | Staf Teknikal dan Operasi | 42 | `sppdnew.realisasi.update` | langkah check/verif dokumen salah penerima |

- Dua trigger ADM juga mengambil nama jabatan lewat `apam.positions p ON p.position_id = s.position_id`; setelah 13 id itu id HR sehingga nama salah. Diganti ke `ptpemaco_hr_production.positions p ON p.id = ...`.
- Trigger yang mencari lewat `employe_id` atau `id_base` (`add_to_doc_1`, `esign.after_add/after_update/after_review2`, `sppdnew.ss/log/drafqw`, `admnew.insert_to_dispo2`) tidak diubah: pemegang `id_base` 3, 4, 6 sama antara view lama dan baru. Yang terdampak hanya karyawan yang tidak ada di view baru (nonaktif/tanpa jabatan di HR) untuk dokumen BARU; riwayat lama tidak berubah (mis. `0001` punya 66 SPPD lama).
- Trigger `pengajuan` (`ApprovedPengajuan*`) memakai `divisions.organization_id = 20` (PUK sama di view lama dan baru) dan id 6, 3: tidak perlu diubah.
- Hasil uji lokal: 42 kombinasi (pengaju x penandatangan) pada `admnew.surats`: langkah approval (view lama + trigger lama) identik dengan (view baru + trigger baru). Dengan view baru + trigger LAMA, paraf Sekretaris Perusahaan hilang di semua kasus. Trigger `sppds` dan `realisasi` baru diuji pada level teks/id (insert SPPD/realisasi belum disimulasikan).
- Paraf Sekretaris Komisaris baru muncul bila SEKKOM-01 memegang jabatan id 68 di HR.

## 5. Efek pada Surat Masuk / disposisi (database ADM)
View `list_surat_masuk` dan `list_cc` serta trigger `dispo`/`notif_cc` mencocokkan `struktur_lengkap_oke.position_id` dengan `id_penerima`.
- **Server**: semua id jabatan yang dipakai ADM (3, 4, 5, 6, 11) sama di apam dan HR (nama sama), jadi tidak ada id yang perlu dimigrasi.
- **Lokal** (id berbeda; migrasi id dicoba di salinan lokal): 100% baris terpetakan, tidak ada penerima yang menjadi kosong. Penerima aktif berubah untuk 18 disposisi (5 belum ditindaklanjuti: pindah dari kotak masuk `202208058K` ke `202311083K` karena PUK SPI berubah) dan 25 cc (9 belum dibaca). Penyebabnya perubahan PUK/PS di bagian 3, bukan salah pemetaan.
- Disposisi dan surat masuk baru menyimpan id jabatan HR (kode backend sudah menyesuaikan).

## 6. Risiko dan pemeriksaan
**Risiko**
1. **Satu jendela perawatan**: 13, 17, dan deploy kode harus berurutan tanpa pemakaian di antaranya (maintenance on -> 13 -> 17 -> 21 -> deploy kode -> maintenance off). Selang waktu membuat id lama/baru bercampur (trigger masih lama dengan view baru, atau sebaliknya).
2. **Rollback ada batas waktu**: 22 + 18 + 14 mengembalikan view dan trigger (teruji); 28 dan 30 mengembalikan data karyawan dan PIC project dari tabel backupnya.
3. **Skrip tidak boleh dijalankan dua kali**: 27 dan 29 membuat tabel backup tanpa IF NOT EXISTS sehingga berhenti di error pertama. 17 juga berhenti bila dijalankan dua kali atau definisi trigger sudah berubah (cek MD5, teruji); 17 dan 18 wajib dijalankan dengan `mysql --comments`.
4. **Perubahan HR langsung berlaku**: tidak ada jeda sinkron. Salah input di HR (nonaktif, pindah jabatan, PS/PIC) langsung mengubah atasan, rantai approval, PUK, dan penerima disposisi.
5. **Karyawan tanpa jabatan di HR / belum punya akun apam**: tidak tampil (aplikasi yang join ke view kehilangan barisnya) atau tampil tanpa `user_id`. Di server hanya 2 karyawan HR aktif yang emailnya tidak cocok dengan akun apam (SEKKOM-01 bila email HR belum sama dengan akun apam, dan akun uji `99` / `eg_Employee@testmail.x` yang memang tidak punya akun apam).
6. **Performa**: view kini join ke tabel HR dan subquery pemegang jabatan diulang 5 kali. Belum diukur di production (`InvController` pernah lambat karena view ini).
7. **Jabatan HR yang diubah/dihapus** mengubah id yang dipakai trigger (68, 51, 43, 42): 17 memeriksa nama jabatannya saat dipasang, tetapi tidak setelahnya.
8. **Nama skema**: skrip memakai `ptpemaco_apam`, `ptpemaco_admnew`, `ptpemaco_sppdnew`, `ptpemaco_hr_production` (sesuai dump). Sesuaikan bila nama di server berbeda.

**Pemeriksaan** (sebelum dan sesudah; tes tiap langkah ada di `TUTORIAL.md`)
```sql
-- karyawan apam yang akan hilang dari view (nonaktif / tanpa jabatan / tidak ada di HR)
SELECT o.employe_id, o.first_name FROM ptpemaco_apam.struktur_lengkap_oke o
 LEFT JOIN ptpemaco_hr_production.users u ON u.employe_id = o.employe_id
 LEFT JOIN ptpemaco_hr_production.employee_positions p ON p.employe_id = o.employe_id
WHERE u.id IS NULL OR u.active = 0 OR p.id IS NULL;
-- sesudah 13: jumlah baris = karyawan aktif berjabatan di HR, dan tidak ada employe_id ganda
SELECT employe_id, COUNT(*) FROM ptpemaco_apam.struktur_lengkap_oke GROUP BY employe_id HAVING COUNT(*) > 1;
```
Uji manual: daftar Surat Masuk (semua, ke saya, tinjut, cc), buat surat masuk dengan memilih direksi, disposisi ke direksi/divisi + cc, cek notifikasi penerima, buat surat keluar (langkah approval), ajukan SPPD (tarif sesuai jabatan asli).

## 7. Data karyawan modul project / daily / task / tiket (21), public key (23), pembersihan (25)
Temuan: model `Employe` membaca tabel apam `employees` (nama, `position_id` apam, status) sehingga project/daily/task/tiket memakai data lama; karyawan baru dari HR tidak punya baris di sana. `employees` tidak bisa dihapus: dirujuk FK 15 tabel (daily, tickets, projects, tasks, dst.) dan menyimpan `user_id` serta `public_key`.
- **21**: view `employees_hr` (kolom sama dengan `employees`; nama, jabatan id HR, status, data pribadi dari HR) dan 5 view apam dialihkan. Hasil uji lokal: jumlah baris view sama; 367 baris nama PIC berubah karena nama mengikuti HR (mis. `Ikhsan Fitrawan Fonn` menjadi `Ikhsan Fitrawan Fonna Lajuba`); rollback 22 mengembalikan definisi persis asli.
- **Kode Laravel (branch `synch`)**: `Employe` membaca `employees_hr`; penulisan lewat `EmployeBase` (tabel `employees`); `getEmployeDivision` dan relasi `position` dari HR; query mentah (Project, Notifikasi, Komentar, Klien, Karyawan) memakai `employees_hr` dan tabel jabatan HR (id HR, `division_id` = organization_id); `Employe::employeId()` punya cadangan lewat `employees_hr` untuk karyawan baru; `Master/PositionController` tidak lagi menulis tabel `struktur`. Diuji lewat tinker terhadap salinan lokal (query jalan, hasil sesuai).
- **23**: `public_key` dipusatkan di HR (tabel baru `employee_public_keys`); kode menulis key baru ke HR dan ke `employees`. Private key tidak pindah (berkas terenkripsi di storage apam). Uji lokal: 20 key tersalin dan terbaca via view; `CryptoService` menyimpan ke HR dan membaca kembali; rollback 24 benar.
- **25**: tabel `nota_dinas`, `struktur`, `personal_access_tokens`, `task_approvals` dan view `gsdgsd`, `kpi_divisi2` diganti nama `zz_hapus_*` (hapus permanen manual nanti). Prasyarat dicek skrip; rollback 26 teruji.
- **Status tabel `employees`, `positions`, `organizations` setelah sync** (audit kode branch `synch` + server):
  - `employees` (158): MASIH DIPAKAI. FK dari 16 kolom (daily, daily_comments/logs, tickets.pic/requester, projects, project_tasks, project_task_pics/approval/favorite, project_histories, project_notifications, activity_bases); kaitan login `user_id` (`Employe::employeId`, notifikasi admin di Auth2/Perusahaan); `public_key`. Kolom nama/jabatan/status/data pribadi sudah tidak jadi sumber (diganti `employees_hr`) tetapi masih dibaca view di skema lain (sppdnew, esign, admnew) dan ditulis `EmployeController`/`ClientController`.
  - `positions` (87): TIDAK dipakai lagi oleh view paket 13 maupun modul project/daily/task/tiket/notifikasi (kini tabel jabatan HR). Sisa pemakai: trigger `sppds` (18 join `apam.positions p ON p.position_id = 13`, hanya nama jabatan id 13), API integrasi `Master/PositionController` dan pencarian `position_code` di `EmployeController`, FK `employees.position_id` (dan `struktur`), fallback `Employe::getEmployeDivision` untuk karyawan yang tidak ada di HR, view `v_active_position_holder` (tanpa rujukan kode). Kandidat dihapus pada tahap berikut, setelah semuanya itu dialihkan; sekaligus 01 dan rute `insert-code` tidak diperlukan lagi.
  - `organizations` (25): MASIH DIPAKAI. Dibaca 13 (`divisions`, aturan `is_division = 1`); FK `projects.division` dan `project_stages.division` (220 baris masing-masing); view `list_task_final`, `kpi_divisi`, `admnew.list_surat`; kode `StaticAdmController`, `ProjectController`, `TaskController`, `Master/OrganizationController`. Id divisi HR = `organization_id` apam (1:1), sehingga join `positions.division_id` (HR) ke `organizations` benar.
- **Risiko**: (a) kode baru WAJIB didahului 21 (tanpa view, semua pembacaan karyawan gagal); (b) `employees_hr` membaca tabel HR langsung: cepat (milidetik) tetapi perubahan HR langsung tampak; (c) query mentah memakai nama database HR dari `config('database.connections.hr.database')` dan join lintas database pada server yang sama; (d) view di skema lain yang memakai `employees` (sppdnew: `list_approval`, `list_sppd`, `list_sppd2`, `list_sppd_ringan`, `logs_pengajuan`; esign: `list_verif`; admnew: `list_surat`, `approved_document`) belum dialihkan dan masih menampilkan nama dari `employees`; (e) trigger `sppds` masih memakai `apam.positions` untuk nama jabatan id 13 (id sama dengan HR).
