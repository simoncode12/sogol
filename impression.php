<?php
// Nonaktifkan pelaporan error agar tidak merusak output gambar
error_reporting(0);
ini_set('display_errors', 0);

// Sertakan koneksi database
require_once __DIR__ . '/admin/db.php';

// Ambil Impression ID dari URL
$impression_id = $_GET['id'] ?? null;

if ($impression_id) {
    try {
        // Cari event 'bid' yang sesuai untuk mendapatkan detail lainnya
        $stmt_bid = $pdo->prepare("SELECT * FROM rtb_events WHERE event_type = 'bid' AND impression_id = ? LIMIT 1");
        $stmt_bid->execute([$impression_id]);
        $bid_event = $stmt_bid->fetch();

        if ($bid_event) {
            // Catat event 'impression'
            $stmt_log = $pdo->prepare(
                "INSERT INTO rtb_events (event_type, impression_id, supply_endpoint_id, demand_campaign_id, site_id, country) 
                 VALUES ('impression', ?, ?, ?, ?, ?)"
            );
            $stmt_log->execute([
                $impression_id,
                $bid_event['supply_endpoint_id'],
                $bid_event['demand_campaign_id'],
                $bid_event['site_id'],
                $bid_event['country']
            ]);
        }
    } catch (Exception $e) {
        // Jika ada error, catat di log server tapi jangan tampilkan apa-apa
        error_log("Impression logging failed: " . $e->getMessage());
    }
}

// Kirim header untuk gambar GIF
header('Content-Type: image/gif');

// Tampilkan output gambar GIF transparan 1x1 piksel
echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICRAEAOw==');
exit;