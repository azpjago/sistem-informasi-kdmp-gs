# Koperasi Desa Merah Putih Ganjar Sabar - Sistem Informasi
🏢 Tentang Sistem
Sistem Informasi Koperasi Desa Merah Putih Ganjar Sabar merupakan platform digital yang dikembangkan untuk mempermudah pekerjaan pengurus Koperasi dalam mengelola operasional harian, dalam rangka menyukseskan program strategis Nasional Bapak Presiden Prabowo Subianto.

🎯 Visi & Misi
Visi
Mewujudkan sistem manajemen koperasi yang modern, efisien, dan transparan untuk mendukung kemajuan ekonomi desa.

Misi
Mempermudah pengelolaan inventaris dan stok barang
Meningkatkan efisiensi proses pengiriman dan distribusi
Memberikan akses monitoring real-time bagi pengurus
Mendukung program strategis Nasional dalam penguatan ekonomi desa

✨ Fitur Utama
🔐 Autentikasi & Keamanan
Multi-level user authentication (Ketua, Pengurus, Gudang)
Role-based access control
Secure session management

📦 Manajemen Produk & Inventory
Kelola produk eceran dan paket
Monitoring stok real-time
Sistem konversi inventory ke produk
Stock Opname dengan approval system

🚚 Sistem Pengiriman
Manajemen kurir dan armada
Tracking status pengiriman
Assign kurir otomatis
Monitoring performa kurir

💰 Monitoring Penjualan
Real-time sales dashboard
Laporan keuangan harian/bulanan
Tracking pesanan dan pembayaran
Analisis profitabilitas

📊 Laporan & Analytics
Laporan stok dan inventory
Laporan penjualan dan pendapatan
Performance monitoring
Audit trail system

👥 Role Pengguna
🎖️ Ketua
Approval Stock Opname
Manajemen pengurus
Laporan keuangan lengkap
Monitoring strategis
Setting sistem

👨‍💼 Pengurus
Monitoring penjualan harian
Kelola produk dan harga
Input data pesanan
Tracking pengiriman

📦 Gudang
Management inventory
Stock Opname
Kelola barang masuk/keluar
Monitoring stok

🛠️ Teknologi yang Digunakan
Backend
PHP - Bahasa pemrograman utama
MySQL - Database management system
Apache - Web server
Frontend
HTML5 - Struktur halaman
CSS3 dengan Bootstrap 5 - Styling dan responsive design
JavaScript dengan jQuery - Interaktivitas client-side
DataTables - Tabel interaktif
Font Awesome - Icons
Libraries & Tools
Chart.js - Visualisasi data

Prerequisites
PHP 7.4 atau lebih tinggi
MySQL 5.7 atau lebih tinggi
Apache Web Server
Composer (optional)

Langkah Instalasi
Clone Repository

bash
git clone https://github.com/username/kdmpgs-system.git
cd kdmpgs-system
Setup Database

sql
-- Import file database kdmpgs-v2.sql
-- atau jalankan query setup manual
Konfigurasi Koneksi Database

php
// Edit file config/database.php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'kdmpgs - v2';
Setup Folder Permissions

bash
chmod 755 uploads/
chmod 755 assets/
Akses Aplikasi

text
http://localhost/kdmpgs-system/dashboard.php

🔧 Konfigurasi
Default Login
Ketua: ketua / password
Pengurus: pengurus / password
Gudang: gudang / password

Environment Setup
Pastikan konfigurasi PHP berikut diaktifkan:
file_uploads = On
upload_max_filesize = 10M
post_max_size = 10M
session.save_path writable

📈 Kontribusi Program Strategis Nasional
Sistem ini mendukung program strategis Nasional Bapak Presiden Prabowo Subianto dalam hal:

🏘️ Penguatan Ekonomi Desa
Digitalisasi UMKM dan koperasi
Peningkatan akses pasar digital
Efisiensi rantai pasok desa

📊 Transparansi Keuangan
Sistem pelaporan yang akurat
Audit trail untuk accountability
Monitoring real-time keuangan koperasi

👥 Pemberdayaan Masyarakat
Pelatihan digital skills
Penciptaan lapangan kerja
Pengembangan entrepreneurship

🤝 Kontribusi
Kami menerima kontribusi untuk pengembangan sistem ini. Silakan:
Fork repository
Buat feature branch
Commit perubahan
Push ke branch
Buat Pull Request

📄 Lisensi
Distributed under the MIT License. See LICENSE untuk detail lebih lanjut.

👥 Tim Pengembang
Koperasi Desa Merah Putih Ganjar Sabar
Pengurus Koperasi
Tim Teknologi Informasi
Relawan Digital Desa

📞 Kontak & Support
Untuk pertanyaan dan dukungan teknis:
Email: kdmpganjarsabar@gmail.com
Telepon: 62882002354299
Alamat: Kantor Koperasi Desa Merah Putih Ganjar Sabar
Cropper.js - Image processing

Bootstrap Icons - Icon toolkit
