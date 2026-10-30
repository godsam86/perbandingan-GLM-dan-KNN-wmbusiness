USE wm99;

DELETE FROM tb_hasil_prediksi;
DELETE FROM tb_transaksi;
DELETE FROM tb_evaluasi;
DELETE FROM tb_koefisien_glm;
DELETE FROM tb_riset;
DELETE FROM tb_log_activity;

ALTER TABLE tb_hasil_prediksi AUTO_INCREMENT = 1;
ALTER TABLE tb_transaksi AUTO_INCREMENT = 1;
ALTER TABLE tb_evaluasi AUTO_INCREMENT = 1;
ALTER TABLE tb_koefisien_glm AUTO_INCREMENT = 1;
ALTER TABLE tb_riset AUTO_INCREMENT = 1;
ALTER TABLE tb_log_activity AUTO_INCREMENT = 1;

USE wm99;

UPDATE users
SET password = '$2y$10$wUkyTxQRwgCwO/OqY/Ls6OzGcwypkvdxaif2sMhWBK1xtU8vnNB9C'
WHERE username = 'admin';