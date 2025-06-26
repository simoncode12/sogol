# AdStart.click - Custom Ad Server & SSP Platform

Selamat datang di AdStart.click, sebuah platform Ad Server dan Supply-Side Platform (SSP) kustom yang dibangun dari awal. Proyek ini dirancang untuk menangani berbagai model bisnis periklanan digital, mulai dari penayangan iklan internal hingga mediasi dan arbitrase traffic secara real-time melalui protokol RTB (Real-Time Bidding).

---

## ✨ Fitur Utama

- **Mesin Lelang Terpadu (Unified Auction):** Menjalankan lelang internal untuk publisher sendiri, di mana kampanye internal (RON) dapat bersaing langsung dengan kampanye eksternal (RTB) untuk memaksimalkan pendapatan.
- **Model Bisnis Arbitrase:** Mampu membeli traffic dari satu sumber (mitra Supply) dan menjualnya ke sumber lain (mitra Demand), dengan profit dari selisih harga bid real-time.
- **Dukungan Format Iklan Multi-Format:**
    - **Banner**: Penayangan iklan gambar standar.
    - **VAST (Pre-roll/In-stream)**: Penayangan iklan video melalui VAST tag.
- **Panel Admin Komprehensif:** Antarmuka web untuk mengelola semua aspek platform:
    - **Dashboard**: Visualisasi metrik kunci dan performa platform secara real-time.
    - **Manajemen Kampanye (Demand)**: Mengatur kampanye RTB (eksternal) dan RON (iklan langsung/internal).
    - **Manajemen Publisher/Partner**: Mengelola mitra penyedia traffic dan kesepakatan bagi hasil (`Revenue Share`).
    - **Manajemen Situs & Zona**: Mendefinisikan situs publisher dan zona iklan spesifik untuk menghasilkan Ad Tag.
    - **Manajemen Kategori**: Mengelola kategori iklan untuk penargetan.
    - **Laporan Statistik**: Halaman laporan canggih dengan filter tanggal dan pengelompokan data berdasarkan berbagai dimensi (Tanggal, Kampanye, Negara, Situs, Format).
- **Sistem Logging Tingkat Lanjut**: Menggunakan tabel log berbasis event (`rtb_events`) untuk melacak seluruh corong (funnel) RTB, mulai dari `request`, `bid`, `impression`, hingga `error`.
- **Fleksibilitas Integrasi**: Dilengkapi dengan pengaturan per-endpoint untuk menyesuaikan format harga bid (per-impresi vs. CPM) agar kompatibel dengan berbagai mitra.

## 🛠️ Teknologi yang Digunakan

- **Backend**: PHP 8+ (dengan PDO)
- **Database**: MySQL / MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (ES6)
- **Framework/Library**:
    - [Bootstrap 5](https://getbootstrap.com/): Untuk layout dan komponen UI di panel admin.
    - [Chart.js](https://www.chartjs.org/): Untuk visualisasi data dan grafik di dashboard.
    - [Video.js](https://videojs.com/) dengan plugin [Google IMA](https://github.com/googleads/videojs-ima): Untuk penayangan iklan video VAST.

## 📂 Struktur Proyek
├── public_html/
│   ├── admin/
│   │   ├── assets/
│   │   │   └── css/
│   │   │       └── style.css
│   │   ├── includes/
│   │   │   ├── header.php
│   │   │   ├── footer.php
│   │   │   └── sidebar.php
│   │   ├── index.php         (Dashboard)
│   │   ├── campaigns.php
│   │   ├── publishers.php
│   │   ├── sites.php
│   │   ├── zones.php
│   │   ├── categories.php
│   │   ├── reports.php
│   │   └── ... (file edit lainnya)
│   ├── ad_tag.js             (Skrip Ad Tag untuk publisher banner)
│   ├── impression.php        (Impression Tracker Pixel)
│   ├── rtb.php               (Ad Server Inti / RTB Engine)
│   ├── adtest.html           (Halaman untuk tes iklan)
│   └── index.php             (Redirect ke panel admin)
└── ..

## 🚀 Instalasi dan Pengaturan

1.  **Clone Repositori**:
    ```bash
    git clone [URL_REPOSITORI_ANDA] adstart.click
    cd adstart.click
    ```

2.  **Database**:
    - Buat sebuah database baru di server MySQL Anda (misalnya, `adstart_db`).
    - Impor file `schema.sql` berikut ke dalam database yang baru Anda buat. File ini berisi semua struktur tabel yang diperlukan.

    <details>
    <summary>Klik untuk melihat schema.sql</summary>

    ```sql
    -- DUMP LENGKAP STRUKTUR DATABASE ADSTART.CLICK
    
    CREATE TABLE `ad_categories` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    
    CREATE TABLE `ad_formats` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `width` int(11) NOT NULL,
      `height` int(11) NOT NULL,
      `format_name` varchar(100) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `size` (`width`,`height`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    CREATE TABLE `campaigns` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `status` enum('active','paused') NOT NULL DEFAULT 'active',
      `campaign_type` enum('rtb','ron') NOT NULL DEFAULT 'rtb',
      `ad_type` enum('banner','native','vast','popunder') NOT NULL DEFAULT 'banner',
      `category_id` int(11) NOT NULL,
      `rtb_endpoint_url` text,
      `ron_adm` text,
      `ron_bid_cpm` decimal(10,6) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    CREATE TABLE `campaign_formats` (
      `campaign_id` int(11) NOT NULL,
      `format_id` int(11) NOT NULL,
      PRIMARY KEY (`campaign_id`,`format_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    CREATE TABLE `publishers` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `email` varchar(255) NOT NULL,
      `revenue_share` decimal(5,2) NOT NULL DEFAULT '70.00',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    CREATE TABLE `rtb_events` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `event_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `event_type` enum('request','bid','win','error','click','impression') NOT NULL,
      `impression_id` varchar(255) DEFAULT NULL,
      `supply_endpoint_id` int(11) DEFAULT NULL,
      `demand_campaign_id` int(11) DEFAULT NULL,
      `site_id` int(11) DEFAULT NULL,
      `country` char(3) DEFAULT NULL,
      `bid_price` decimal(10,6) DEFAULT NULL,
      `payout_price` decimal(10,6) DEFAULT NULL,
      `error_message` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_event_type_time` (`event_type`,`event_timestamp`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    CREATE TABLE `rtb_endpoints_generated` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `endpoint_hash` varchar(32) NOT NULL,
      `publisher_id` int(11) DEFAULT NULL,
      `site_id` int(11) DEFAULT NULL,
      `ad_format` enum('banner','native','vast','popunder') NOT NULL DEFAULT 'banner',
      `status` enum('active','paused') NOT NULL DEFAULT 'active',
      `bid_price_is_cpm` tinyint(1) NOT NULL DEFAULT '0',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `endpoint_hash` (`endpoint_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    CREATE TABLE `sites` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `publisher_id` int(11) NOT NULL,
      `domain` varchar(255) NOT NULL,
      `category_id` int(11) NOT NULL,
      `status` enum('active','pending','rejected') NOT NULL DEFAULT 'pending',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    CREATE TABLE `vast_creatives` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `vast_xml` text NOT NULL,
      `is_url` tinyint(1) NOT NULL DEFAULT '0',
      `status` enum('active','paused') NOT NULL DEFAULT 'active',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    CREATE TABLE `zones` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `site_id` int(11) NOT NULL,
      `ad_type` enum('banner','vast') NOT NULL DEFAULT 'banner',
      `format_id` int(11) DEFAULT NULL,
      `status` enum('active','paused') NOT NULL DEFAULT 'active',
      PRIMARY KEY (`id`),
      KEY `idx_site_id` (`site_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ```
    </details>

3.  **Konfigurasi**:
    - Buka file `admin/db.php`.
    - Ganti nilai `$DB_HOST`, `$DB_NAME`, `$DB_USER`, dan `$DB_PASS` dengan kredensial database Anda.

4.  **Web Server**:
    - Arahkan *document root* dari domain Anda (misal: `adstart.click`) ke direktori `public_html`.
    - Pastikan `mod_rewrite` diaktifkan jika Anda ingin menggunakan URL yang lebih bersih di masa depan.

## 💡 Alur Kerja Dasar

1.  **Daftarkan Publisher**: Buka `Admin > Publishers` untuk menambahkan mitra penyedia traffic dan mengatur `Revenue Share` mereka.
2.  **Buat Kampanye**: Buka `Admin > Campaigns` untuk menambahkan sumber Demand.
    - **Tipe RTB**: Untuk sumber eksternal seperti ExoClick. Harga akan didapat secara real-time.
    - **Tipe RON**: Untuk iklan internal. Anda harus mengatur harga bid CPM secara manual.
3.  **Buat Zona**: Buka `Admin > Zones` untuk membuat slot iklan pada situs publisher.
4.  **Dapatkan Ad Tag**: Klik tombol "Get Ad Tag" pada halaman Zona untuk mendapatkan kode JavaScript/URL yang akan dipasang di situs publisher.
5.  **Tes**: Gunakan `adtest.html` untuk memverifikasi bahwa Ad Tag dapat menampilkan iklan dari lelang.
6.  **Analisis**: Buka `Admin > Reports` dan `Admin > Dashboard` untuk melihat performa platform.

## 🛣️ Roadmap & Pengembangan Selanjutnya

Proyek ini memiliki fondasi yang kuat, namun ada banyak fitur yang bisa ditambahkan:
- **Click Tracking**: Membuat `click.php` untuk melacak klik dan menghitung metrik `eCPC`.
- **Win Notification**: Membuat `win.php` untuk menerima notifikasi kemenangan dari mitra SSP, yang akan membuat metrik `Wins` dan `View Rate` menjadi akurat.
- **Portal Login Publisher**: Halaman login terpisah untuk publisher agar mereka bisa melihat statistik dan pendapatan mereka sendiri.
- **Opsi Penargetan Lanjutan**: Menambahkan penargetan berdasarkan Geografi (Negara/Kota), Tipe Perangkat, dll. pada level Kampanye.

---
