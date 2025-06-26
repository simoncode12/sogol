<?php
require_once 'db.php';

// Filter
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$group_by = $_GET['group_by'] ?? 'date';

// WHERE clause yang konsisten untuk periode tanggal
$where_clause = 'WHERE DATE(ev.event_timestamp) BETWEEN ? AND ?';

// GROUP BY clause
$group_by_fields = [
    'date' => 'DATE(ev.event_timestamp)',
    'campaign' => 'ev.demand_campaign_id',
    'country' => 'ev.country',
    'site' => 'ev.site_id',
    'format' => 'ev.ad_format'
];
$group_by_sql = $group_by_fields[$group_by] ?? $group_by_fields['date'];
$group_by_title = ucwords(str_replace('_', ' ', $group_by));

// SELECT clause untuk nama grup
$select_fields = [
    'date' => 'DATE_FORMAT(ev.event_timestamp, "%d %b %Y") as group_name',
    'campaign' => 'c.name as group_name',
    'country' => 'ev.country as group_name',
    'site' => 's.domain as group_name',
    'format' => 'ev.ad_format as group_name'
];
$select_sql = $select_fields[$group_by] ?? $select_fields['date'];

// LEFT JOIN clause untuk mendapatkan nama, bukan hanya ID
$join_sql = "
    LEFT JOIN campaigns c ON ev.demand_campaign_id = c.id
    LEFT JOIN sites s ON ev.site_id = s.id
";

// Query SQL utama yang komprehensif
$query = "
    SELECT
        {$select_sql},
        COUNT(ev.id) AS total_requests,
        COUNT(CASE WHEN ev.event_type = 'bid' THEN 1 END) AS total_bids,
        COUNT(CASE WHEN ev.event_type = 'impression' THEN 1 END) AS total_views,
        COUNT(CASE WHEN ev.event_type = 'win' THEN 1 END) AS total_wins,
        COUNT(CASE WHEN ev.event_type = 'error' THEN 1 END) AS total_errors,
        COUNT(CASE WHEN ev.event_type = 'click' THEN 1 END) AS total_clicks,
        SUM(CASE WHEN ev.event_type = 'bid' THEN (payout_price - bid_price) END) AS gross_profit,
        SUM(CASE WHEN ev.event_type = 'bid' THEN payout_price END) AS total_revenue
    FROM
        rtb_events ev
    {$join_sql}
    {$where_clause}
    GROUP BY
        group_name
    ORDER BY
        group_name DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$start_date, $end_date]);
$report_data = $stmt->fetchAll();

$page_title = "SSP Statistics";
include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">SSP Statistics Report</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="reports.php" class="row g-3 align-items-end bg-light p-3 rounded">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-3">
                <label for="group_by" class="form-label">Group By</label>
                <select name="group_by" id="group_by" class="form-select">
                    <option value="date" <?php if($group_by === 'date') echo 'selected'; ?>>Date</option>
                    <option value="campaign" <?php if($group_by === 'campaign') echo 'selected'; ?>>Campaign</option>
                    <option value="country" <?php if($group_by === 'country') echo 'selected'; ?>>Country</option>
                    <option value="site" <?php if($group_by === 'site') echo 'selected'; ?>>Site</option>
                    <option value="format" <?php if($group_by === 'format') echo 'selected'; ?>>Ad Format</option>
                </select>
            </div>
            <div class="col-md-3">
                 <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark text-center">
                    <tr>
                        <th><?php echo $group_by_title; ?></th>
                        <th>Requests</th>
                        <th>Bids Sent</th>
                        <th>Views</th>
                        <th>Errors</th>
                        <th>Fill Rate</th>
                        <th>View Rate</th>
                        <th>eCPM</th>
                        <th>Gross Profit</th>
                    </tr>
                </thead>
                <tbody class="text-center">
                    <?php if(empty($report_data)): ?>
                        <tr><td colspan="8" class="text-center">No data available for the selected filters.</td></tr>
                    <?php else: ?>
                        <?php foreach($report_data as $row):
                            $fill_rate = ($row['total_requests'] > 0) ? ($row['total_bids'] / $row['total_requests'] * 100) : 0;
                            $view_rate = ($row['total_bids'] > 0) ? ($row['total_views'] / $row['total_bids'] * 100) : 0;
                            // eCPM dihitung berdasarkan total revenue dibagi Views (impresi) yang sebenarnya
                            $ecpm = ($row['total_views'] > 0) ? (($row['total_revenue'] / $row['total_views']) * 1000) : 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['group_name'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($row['total_requests']); ?></td>
                                <td><?php echo number_format($row['total_bids']); ?></td>
                                <td class="fw-bold"><?php echo number_format($row['total_views']); ?></td>
                                <td class="text-danger"><?php echo number_format($row['total_errors']); ?></td>
                                <td><?php echo number_format($fill_rate, 2); ?>%</td>
                                <td class="text-primary"><?php echo number_format($view_rate, 2); ?>%</td>
                                <td class="text-success fw-bold">$<?php echo number_format($ecpm, 4); ?></td>
                                <td class="fw-bold">$<?php echo number_format($row['gross_profit'] ?? 0, 5); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="form-text mt-2">
            * <strong>Requests</strong>: Total permintaan yang masuk dari mitra traffic.<br>
            * <strong>Views</strong>: Jumlah impresi yang berhasil tercatat oleh impression pixel. <br>
            * <strong>Fill Rate</strong>: Persentase permintaan yang berhasil dijawab dengan penawaran (Bids/Requests).<br>
            * <strong>View Rate</strong>: Persentase penawaran yang berhasil menjadi tayangan (Views/Bids).<br>
            * <strong>eCPM</strong>: Pendapatan efektif per 1000 tayangan (berdasarkan Views).
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
