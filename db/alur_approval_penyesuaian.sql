-- Penyesuaian aturan SPPD "Komisaris Utama dan Komisaris": tiga direktur diparaf berurutan
-- Direktur Umum dan Keuangan (jabatan 6), Direktur Pengembangan Bisnis (4), Direktur Komersial (5).
-- Sebelumnya urutannya mengikuti pemegang jenjang 4 ke-1/2/3 (tidak terjamin). Hanya mengubah data aturan, bukan trigger.
-- Aman dijalankan ulang. Bisa juga diubah dari Setting > Alur Approval.
UPDATE ptpemaco_esign.alur_langkah l
JOIN ptpemaco_esign.alur_aturan a ON a.id = l.id_aturan
SET l.sumber = 'posisi', l.urutan_sumber = 0,
    l.nilai = CASE l.urutan WHEN 2 THEN 6 WHEN 3 THEN 4 WHEN 4 THEN 5 END
WHERE a.modul = 'sppd' AND a.nama = 'Komisaris Utama dan Komisaris' AND l.tipe = 'paraf' AND l.urutan IN (2, 3, 4);

SELECT l.urutan, l.tipe, l.sumber, l.nilai FROM ptpemaco_esign.alur_langkah l
JOIN ptpemaco_esign.alur_aturan a ON a.id = l.id_aturan
WHERE a.modul = 'sppd' AND a.nama = 'Komisaris Utama dan Komisaris' ORDER BY l.urutan;
