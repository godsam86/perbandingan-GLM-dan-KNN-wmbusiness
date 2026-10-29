-- ============================================================
-- Database: wm99
-- Sistem Prediksi Tren Penjualan WMBusiness99 Medan
-- Sesuai class diagram (8 tabel, relasi persis)
-- ============================================================

CREATE DATABASE IF NOT EXISTS wm99
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE wm99;
-- ------------------------------------------------------------
-- Tabel users
-- ------------------------------------------------------------
CREATE TABLE users (
  id_user      INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(50)  NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(50),
  role         VARCHAR(10)  DEFAULT 'admin',
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel tb_produk
-- Relasi: users 1-N tb_produk (created_by)
-- ------------------------------------------------------------
CREATE TABLE tb_produk (
  id_produk   INT AUTO_INCREMENT PRIMARY KEY,
  nama_produk VARCHAR(50)  NOT NULL,
  kategori    VARCHAR(50),
  harga       DECIMAL(10,2),
  stok        INT DEFAULT 0,
  created_by  INT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id_user) ON DELETE SET NULL,
  INDEX idx_nama_produk (nama_produk),
  INDEX idx_kategori (kategori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel tb_riset
-- Relasi: users 1-N tb_riset (created_by)
-- ------------------------------------------------------------
CREATE TABLE tb_riset (
  id_riset    INT AUTO_INCREMENT PRIMARY KEY,
  judul       VARCHAR(200),
  deskripsi   TEXT NULL,
  tipe_riset  VARCHAR(50),
  data_json   TEXT NULL,
  jumlah_data INT,
  status      VARCHAR(20) DEFAULT 'completed',
  created_by  INT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id_user) ON DELETE SET NULL,
  INDEX idx_tipe (tipe_riset)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel tb_log_activity
-- Relasi: users 1-N tb_log_activity
-- ------------------------------------------------------------
CREATE TABLE tb_log_activity (
  id_log     INT AUTO_INCREMENT PRIMARY KEY,
  id_user    INT NULL,
  aksi       VARCHAR(100),
  keterangan TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE SET NULL,
  INDEX idx_user (id_user),
  INDEX idx_aksi (aksi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel tb_transaksi
-- Relasi: tb_produk 1-N tb_transaksi (dijual pada)
-- ------------------------------------------------------------
CREATE TABLE tb_transaksi (
  id_transaksi      INT AUTO_INCREMENT PRIMARY KEY,
  tanggal_transaksi DATE NOT NULL,
  id_produk         INT NOT NULL,
  jumlah_terjual    INT NOT NULL,
  harga_satuan      DECIMAL(10,2),
  total_harga       DECIMAL(10,2),
  label_tren        VARCHAR(20) DEFAULT NULL,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_produk) REFERENCES tb_produk(id_produk) ON DELETE CASCADE,
  INDEX idx_tanggal (tanggal_transaksi),
  INDEX idx_produk_tanggal (id_produk, tanggal_transaksi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel tb_evaluasi
-- Relasi: tb_evaluasi 1-N tb_hasil_prediksi (menghasilkan)
-- ------------------------------------------------------------
CREATE TABLE tb_evaluasi (
  id_evaluasi      INT AUTO_INCREMENT PRIMARY KEY,
  algoritma        VARCHAR(20),
  accuracy         DECIMAL(5,4),
  precision_score  DECIMAL(5,4),
  recall_score     DECIMAL(5,4),
  f1_score         DECIMAL(5,4),
  confusion_matrix TEXT,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_algoritma (algoritma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel tb_koefisien_glm
-- Relasi: tb_koefisien_glm 1-N tb_hasil_prediksi (digunakan)
-- DECIMAL(18,10) agar koefisien kecil tidak terbulatkan 0.
-- ------------------------------------------------------------
CREATE TABLE tb_koefisien_glm (
  id_koefisien INT AUTO_INCREMENT PRIMARY KEY,
  beta0        DECIMAL(18,10),
  beta1        DECIMAL(18,10),
  beta2        DECIMAL(18,10),
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel tb_hasil_prediksi
-- Relasi:
--   tb_evaluasi 1-N tb_hasil_prediksi (menghasilkan)
--   tb_koefisien_glm 1-N tb_hasil_prediksi (digunakan)
--   tb_produk 1-N tb_hasil_prediksi
-- ------------------------------------------------------------
CREATE TABLE tb_hasil_prediksi (
  id_prediksi    INT AUTO_INCREMENT PRIMARY KEY,
  id_produk      INT NULL,
  id_evaluasi    INT NULL,
  id_koefisien   INT NULL,
  algoritma      VARCHAR(20),
  periode        VARCHAR(20),
  hasil_prediksi VARCHAR(20),
  probabilitas   DECIMAL(5,4),
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_produk)    REFERENCES tb_produk(id_produk) ON DELETE CASCADE,
  FOREIGN KEY (id_evaluasi)  REFERENCES tb_evaluasi(id_evaluasi) ON DELETE SET NULL,
  FOREIGN KEY (id_koefisien) REFERENCES tb_koefisien_glm(id_koefisien) ON DELETE SET NULL,
  INDEX idx_produk (id_produk),
  INDEX idx_algoritma (algoritma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Seed admin (password: admin123)
-- Hash bcrypt — sudah diverifikasi
-- ------------------------------------------------------------
INSERT INTO users (username, password, nama_lengkap, role)
VALUES (
  'admin',
  '$2y$10$UKf8jeYaQIkFP22K4TZFdugSVpcOtu.BoPYvy.EmL9vTwzdkNIrCy',
  'Admin WMBusiness99',
  'admin'
);

USE wm99;

INSERT INTO tb_produk (id_produk, nama_produk, kategori, harga, stok, created_by) VALUES
(1,  'Charger iPhone 20W',         'Charger',           15500,   50, 1),
(2,  'Case iPhone 13',             'Casing',            65000,   30, 1),
(3,  'Kabel Data Lightning',       'Kabel data',        21000,   40, 1),
(4,  'Power Bank Mini 5000mAh',    'Power bank',       344000,   20, 1),
(5,  'Headset Gaming RGB',         'Earphone/Headset', 173500,   40, 1),
(6,  'Earphone Kabel Basic',       'Earphone/Headset', 188000,   15, 1),
(7,  'Power Bank 20000mAh',        'Power bank',       323000,   10, 1),
(8,  'Charger Fast Charging 33W',  'Charger',          155000,   50, 1),
(9,  'Kabel Data 3in1',            'Kabel data',        25000,   80, 1),
(10, 'Case Samsung A54',           'Casing',            60000,   25, 1);

SELECT id_produk, nama_produk, kategori, harga FROM tb_produk ORDER BY id_produk;