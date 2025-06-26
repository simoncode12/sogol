<?php
// Harap pastikan Anda memiliki sistem sesi/login untuk melindungi halaman ini.
// Contoh sederhana:
// session_start();
// if (!isset($_SESSION['user_is_admin']) || $_SESSION['user_is_admin'] !== true) {
//     die("Akses ditolak. Silakan login sebagai admin.");
// }

require_once __DIR__ . '/db.php';

// Atur zona waktu ke UTC agar konsisten dengan database
date_default_timezone_set('UTC');

// Logika untuk filter tanggal
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Pastikan tanggal akhir mencakup keseluruhan hari
$end_date_sql = date('Y-m-d 23:59:59', strtotime($end_date));

// =================================================================
// 1. Mengambil Data untuk KPI Utama
// =================================================================

// Total Permintaan Iklan dari tabel rtb_events
$stmt_req = $pdo->prepare("SELECT COUNT(*) as total_requests FROM rtb_events WHERE event_type = 'request' AND event_timestamp BETWEEN ? AND ?");
$stmt_req->execute([$start_date, $end_date_sql]);
$total_requests = $stmt_req->fetchColumn();

// Data Keuangan dan Tayangan dari financial_logs
$stmt_financial = $pdo->prepare(
    "SELECT 
        COUNT(*) as total_impressions,
        SUM(sell_price) as gross_revenue,
        SUM(publisher_revenue) as total_publisher_revenue,
        SUM(platform_profit) as total_platform_profit
    FROM financial_logs 
    WHERE log_timestamp BETWEEN ? AND ?"
);
$stmt_financial->execute([$start_date, $end_date_sql]);
$financial_summary = $stmt_financial->fetch(PDO::FETCH_ASSOC);

$total_impressions = $financial_summary['total_impressions'] ?? 0;
$gross_revenue = $financial_summary['gross_revenue'] ?? 0;
$total_publisher_revenue = $financial_summary['total_publisher_revenue'] ?? 0;
$total_platform_profit = $financial_summary['total_platform_profit'] ?? 0;

// Menghitung Metrik Turunan
$fill_rate = ($total_requests > 0) ? ($total_impressions / $total_requests) * 100 : 0;
$eCPM = ($total_impressions > 0) ? ($total_publisher_revenue / $total_impressions) * 1000 : 0;


// =================================================================
// 2. Mengambil Data untuk Laporan Rincian
// =================================================================

// Laporan Harian
$stmt_daily = $pdo->prepare(
    "SELECT 
        DATE(log_timestamp) as report_date,
        COUNT(*) as impressions,
        SUM(publisher_revenue) as revenue
    FROM financial_logs
    WHERE log_timestamp BETWEEN ? AND ?
    GROUP BY report_date
    ORDER BY report_date DESC"
);
$stmt_daily->execute([$start_date, $end_date_sql]);
$daily_report = $stmt_daily->fetchAll(PDO::FETCH_ASSOC);

// Laporan per Situs
$stmt_site = $pdo->prepare(
    "SELECT 
        s.domain,
        COUNT(fl.id) as impressions,
        SUM(fl.publisher_revenue) as revenue
    FROM financial_logs fl
    JOIN sites s ON fl.site_id = s.id
    WHERE fl.log_timestamp BETWEEN ? AND ?
    GROUP BY s.domain
    ORDER BY revenue DESC"
);
$stmt_site->execute([$start_date, $end_date_sql]);
$site_report = $stmt_site->fetchAll(PDO::FETCH_ASSOC);

// Laporan per Negara
$stmt_country = $pdo->prepare(
    "SELECT 
        country,
        COUNT(id) as impressions,
        SUM(publisher_revenue) as revenue
    FROM financial_logs
    WHERE log_timestamp BETWEEN ? AND ?
    GROUP BY country
    ORDER BY revenue DESC"
);
$stmt_country->execute([$start_date, $end_date_sql]);
$country_report = $stmt_country->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasbor Statistik - AdStart.click</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card-kpi { text-align: center; }
        .card-kpi .display-6 { font-weight: 500; }
        .card-kpi p { color: #6c757d; }
        .table-responsive { max-height: 400px; }
    </style>
</head>
<body>
    <div class="container my-4">
        <h1 class="mb-4">Dasbor Statistik</h1>

        <!-- Filter Tanggal -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="start_date" class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-auto">
                        <label for="end_date" class="form-label">Tanggal Akhir</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-auto align-self-end">
                        <button type="submit" class="btn btn-primary">Terapkan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- KPI Utama -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="card card-kpi">
                    <div class="card-body">
                        <h3 class="display-6"><?= number_format($total_publisher_revenue, 4) ?> $</h3>
                        <p>Pendapatan Publisher</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card card-kpi">
                    <div class="card-body">
                        <h3 class="display-6"><?= number_format($eCPM, 4) ?> $</h3>
                        <p>eCPM Rata-rata</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card card-kpi">
                    <div class="card-body">
                        <h3 class="display-6"><?= number_format($total_impressions) ?></h3>
                        <p>Total Tayangan</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card card-kpi">
                    <div class="card-body">
                        <h3 class="display-6"><?= number_format($total_requests) ?></h3>
                        <p>Total Permintaan</p>
                    </div>
                </div>
            </div>
             <div class="col-md-6 col-lg-3">
                <div class="card card-kpi">
                    <div class="card-body">
                        <h3 class="display-6"><?= number_format($fill_rate, 2) ?> %</h3>
                        <p>Fill Rate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card card-kpi">
                    <div class="card-body">
                        <h3 class="display-6"><?= number_format($gross_revenue, 4) ?> $</h3>
                        <p>Pendapatan Kotor</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card card-kpi">
                    <div class="card-body">
                        <h3 class="display-6"><?= number_format($total_platform_profit, 4) ?> $</h3>
                        <p>Keuntungan Platform</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Laporan Rinci -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">Laporan Harian</div>
                    <div class="card-body table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr><th>Tanggal</th><th>Pendapatan</th><th>Tayangan</th><th>eCPM</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daily_report as $row): ?>
                                    <?php $daily_ecpm = ($row['impressions'] > 0) ? ($row['revenue'] / $row['impressions']) * 1000 : 0; ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['report_date']) ?></td>
                                        <td><?= number_format($row['revenue'], 4) ?> $</td>
                                        <td><?= number_format($row['impressions']) ?></td>
                                        <td><?= number_format($daily_ecpm, 4) ?> $</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">Laporan per Situs</div>
                    <div class="card-body table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr><th>Domain</th><th>Pendapatan</th><th>Tayangan</th><th>eCPM</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($site_report as $row): ?>
                                     <?php $site_ecpm = ($row['impressions'] > 0) ? ($row['revenue'] / $row['impressions']) * 1000 : 0; ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['domain']) ?></td>
                                        <td><?= number_format($row['revenue'], 4) ?> $</td>
                                        <td><?= number_format($row['impressions']) ?></td>
                                        <td><?= number_format($site_ecpm, 4) ?> $</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">Laporan per Negara</div>
                    <div class="card-body table-responsive">
                         <table class="table table-striped table-hover">
                            <thead>
                                <tr><th>Negara</th><th>Pendapatan</th><th>Tayangan</th><th>eCPM</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($country_report as $row): ?>
                                     <?php $country_ecpm = ($row['impressions'] > 0) ? ($row['revenue'] / $row['impressions']) * 1000 : 0; ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['country']) ?></td>
                                        <td><?= number_format($row['revenue'], 4) ?> $</td>
                                        <td><?= number_format($row['impressions']) ?></td>
                                        <td><?= number_format($country_ecpm, 4) ?> $</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>