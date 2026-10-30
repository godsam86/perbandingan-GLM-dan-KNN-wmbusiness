# Sistem Prediksi Tren Penjualan WMBusiness99 Medan

Sistem perbandingan **Generalized Linear Model (GLM)** vs **K-Nearest Neighbor (KNN)** untuk prediksi tren penjualan.

---

## Prasyarat

- **XAMPP** (MySQL/MariaDB)
- **PHP 8+**
- **Composer**
- **Python 3.11+**
- **Browser** (Chrome/Firefox)

---

## Struktur Proyek
wm99/
├── python_ml_service/ ← Backend ML (Python + Flask)
└── php_app/ ← Aplikasi Web (PHP + MySQL)


---

## 1. Setup Database

Jalankan XAMPP → **Start MySQL**.

Buka `http://localhost/phpmyadmin`, lalu **Import** 2 file ini secara berurutan:

1. `php_app/database/schema.sql`
2. `php_app/database/seed_produk.sql`

Atau via Command Line:

```powershell
cd C:\xampp\htdocs\wm99\php_app
C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE IF NOT EXISTS wm99"
C:\xampp\mysql\bin\mysql -u root wm99 < database\schema.sql
C:\xampp\mysql\bin\mysql -u root wm99 < database\seed_produk.sql

Buka Terminal 1 (PowerShell):

powershell
cd C:\xampp\htdocs\wm99\python_ml_service

# Buat virtual environment
python -m venv venv

# Aktifkan
venv\Scripts\activate

# Install dependensi
pip install -r requirements.txt

Buat file .env di folder python_ml_service/:

env
API_KEY=wm99-secret-key-change-me
PORT=5001
Jalankan ML Service:

powershell
python app.py
Biarkan terminal ini terbuka.

Cek: curl http://127.0.0.1:5001/health → {"status":"ok"}

