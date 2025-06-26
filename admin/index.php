<?php
require_once 'db.php';

// ==================================================================
// FETCH DATA FOR KPI CARDS (TODAY'S STATS)
// ==================================================================
$today_date = date('Y-m-d');

// 1. Todays Financials and Impressions
$stmt_today_financials = $pdo->prepare(
    "SELECT 
        COALESCE(SUM(platform_profit), 0) as total_profit, 
        COUNT(*) as total_impressions 
     FROM financial_logs WHERE DATE(log_timestamp) = ?"
);
$stmt_today_financials->execute([$today_date]);
$today_stats = $stmt_today_financials->fetch();

$todays_profit = $today_stats['total_profit'] ?? 0;
$todays_impressions = $today_stats['total_impressions'] ?? 0;

// 2. Active Campaigns
$active_campaigns_result = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status = 'active'");
$active_campaigns = $active_campaigns_result ? $active_campaigns_result->fetchColumn() : 0;

// 3. Total Publishers
$total_publishers_result = $pdo->query("SELECT COUNT(*) FROM publishers");
$total_publishers = $total_publishers_result ? $total_publishers_result->fetchColumn() : 0;

// 4. Calculate success rate (example calculation)
$stmt_success = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM campaigns");
$success_data = $stmt_success ? $stmt_success->fetch() : ['total' => 0, 'active' => 0];
$success_rate = $success_data['total'] > 0 ? ($success_data['active'] / $success_data['total'] * 100) : 0;

// 5. Get recent activity count
$recent_logs_result = $pdo->query("SELECT COUNT(*) FROM financial_logs WHERE log_timestamp >= NOW() - INTERVAL 1 HOUR");
$recent_logs = $recent_logs_result ? $recent_logs_result->fetchColumn() : 0;

// ==================================================================
// FETCH DATA FOR PROFIT CHART (LAST 7 DAYS)
// ==================================================================
$chart_labels = [];
$chart_data = [];

// Siapkan array untuk 7 hari terakhir dengan nilai awal 0
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($date));
    $chart_data[$date] = 0;
}

// Ambil data profit aktual dari database
$stmt_chart = $pdo->query(
    "SELECT 
        DATE(log_timestamp) as date, 
        SUM(platform_profit) as daily_profit 
     FROM financial_logs 
     WHERE log_timestamp >= CURDATE() - INTERVAL 6 DAY 
     GROUP BY DATE(log_timestamp)"
);

if ($stmt_chart) {
    while ($row = $stmt_chart->fetch()) {
        if (isset($chart_data[$row['date']])) {
            $chart_data[$row['date']] = (float)$row['daily_profit'];
        }
    }
}

// Ubah menjadi array numerik yang siap digunakan oleh Chart.js
$chart_data_final = array_values($chart_data);

// ==================================================================
// FETCH RECENT ACTIVITY DATA (SIMPLIFIED)
// ==================================================================
$stmt_recent = $pdo->prepare(
    "SELECT 
        log_timestamp,
        platform_profit,
        impression_id
     FROM financial_logs
     ORDER BY log_timestamp DESC
     LIMIT 5"
);

$recent_activities = [];
if ($stmt_recent && $stmt_recent->execute()) {
    $recent_activities = $stmt_recent->fetchAll();
}

// ==================================================================
// CALCULATE YESTERDAY'S DATA FOR COMPARISON
// ==================================================================
$yesterday_date = date('Y-m-d', strtotime('-1 day'));

$stmt_yesterday = $pdo->prepare(
    "SELECT 
        COALESCE(SUM(platform_profit), 0) as yesterday_profit,
        COUNT(*) as yesterday_impressions
     FROM financial_logs WHERE DATE(log_timestamp) = ?"
);

$yesterday_stats = ['yesterday_profit' => 0, 'yesterday_impressions' => 0];
if ($stmt_yesterday && $stmt_yesterday->execute([$yesterday_date])) {
    $yesterday_stats = $stmt_yesterday->fetch();
}

// Calculate percentage changes
$profit_change = 0;
$impressions_change = 0;

if ($yesterday_stats['yesterday_profit'] > 0) {
    $profit_change = (($todays_profit - $yesterday_stats['yesterday_profit']) / $yesterday_stats['yesterday_profit']) * 100;
}

if ($yesterday_stats['yesterday_impressions'] > 0) {
    $impressions_change = (($todays_impressions - $yesterday_stats['yesterday_impressions']) / $yesterday_stats['yesterday_impressions']) * 100;
}

$page_title = "Dashboard";
include 'includes/header.php';
?>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="modern-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="card-body py-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2 fw-bold">Welcome back, Admin! 👋</h2>
                        <p class="mb-0 opacity-75">Here's what's happening with your ad platform today.</p>
                        <small class="opacity-75">
                            <i class="bi bi-calendar me-1"></i>
                            <?php echo date('l, F j, Y - H:i T'); ?>
                        </small>
                    </div>
                    <div class="col-md-4 text-end">
                        <i class="bi bi-graph-up-arrow" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="card-title">$<?php echo number_format($todays_profit, 5); ?></div>
                        <div class="card-text">Profit Today</div>
                        <div class="mt-2">
                            <?php if ($profit_change > 0): ?>
                                <small class="text-success">
                                    <i class="bi bi-arrow-up"></i> +<?php echo number_format($profit_change, 1); ?>% from yesterday
                                </small>
                            <?php elseif ($profit_change < 0): ?>
                                <small class="text-danger">
                                    <i class="bi bi-arrow-down"></i> <?php echo number_format($profit_change, 1); ?>% from yesterday
                                </small>
                            <?php else: ?>
                                <small class="text-muted">
                                    <i class="bi bi-dash"></i> No change from yesterday
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="bi bi-cash-coin text-white" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="card-title"><?php echo number_format($todays_impressions); ?></div>
                        <div class="card-text">Impressions Today</div>
                        <div class="mt-2">
                            <?php if ($impressions_change > 0): ?>
                                <small class="text-success">
                                    <i class="bi bi-arrow-up"></i> +<?php echo number_format($impressions_change, 1); ?>% from yesterday
                                </small>
                            <?php elseif ($impressions_change < 0): ?>
                                <small class="text-danger">
                                    <i class="bi bi-arrow-down"></i> <?php echo number_format($impressions_change, 1); ?>% from yesterday
                                </small>
                            <?php else: ?>
                                <small class="text-muted">
                                    <i class="bi bi-dash"></i> No change from yesterday
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 60px; height: 60px; background: linear-gradient(135deg, #f093fb, #f5576c);">
                            <i class="bi bi-eye-fill text-white" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="card-title"><?php echo $active_campaigns; ?></div>
                        <div class="card-text">Active Campaigns</div>
                        <div class="mt-2">
                            <small class="text-info">
                                <i class="bi bi-info-circle"></i> <?php echo $recent_logs; ?> logs this hour
                            </small>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 60px; height: 60px; background: linear-gradient(135deg, #84fab0, #8fd3f4);">
                            <i class="bi bi-megaphone-fill text-white" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="card-title"><?php echo $total_publishers; ?></div>
                        <div class="card-text">Total Publishers</div>
                        <div class="mt-2">
                            <small class="text-warning">
                                <i class="bi bi-percent"></i> Success rate: <?php echo number_format($success_rate, 1); ?>%
                            </small>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 60px; height: 60px; background: linear-gradient(135deg, #ffecd2, #fcb69f);">
                            <i class="bi bi-people-fill text-white" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Recent Activity Row -->
<div class="row">
    <!-- Profit Chart -->
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="modern-card">
            <div class="card-header d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi bi-graph-up me-2"></i>7-Day Platform Profit Overview
                </h6>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="autoRefreshSwitch">
                    <label class="form-check-label" for="autoRefreshSwitch">
                        <small>Auto-Refresh (30s)</small>
                    </label>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-area" style="height: 320px; position: relative;">
                    <canvas id="profitChart"></canvas>
                </div>
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Data updates every 30 seconds when auto-refresh is enabled
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="modern-card h-100">
            <div class="card-header">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi bi-clock-history me-2"></i>Recent Activity
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($recent_activities)): ?>
                        <div class="list-group-item text-center py-4">
                            <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">No recent activity</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="list-group-item border-0 py-3">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-success d-flex align-items-center justify-content-center me-3" 
                                         style="width: 40px; height: 40px;">
                                        <i class="bi bi-cash text-white"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark">
                                            $<?php echo number_format($activity['platform_profit'], 5); ?>
                                        </div>
                                        <small class="text-muted">
                                            Impression ID: <?php echo htmlspecialchars($activity['impression_id']); ?>
                                        </small>
                                        <div>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>
                                                <?php echo date('H:i', strtotime($activity['log_timestamp'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light">
                    <div class="text-center">
                        <a href="reports.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-right me-1"></i>View All Activity
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="modern-card">
            <div class="card-header">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bi bi-lightning-fill me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="campaigns.php" class="text-decoration-none">
                            <div class="d-flex align-items-center p-3 bg-light rounded-3 h-100 quick-action-card">
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-3" 
                                     style="width: 50px; height: 50px;">
                                    <i class="bi bi-plus-lg text-white"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">New Campaign</div>
                                    <small class="text-muted">Create RTB campaign</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="publishers.php" class="text-decoration-none">
                            <div class="d-flex align-items-center p-3 bg-light rounded-3 h-100 quick-action-card">
                                <div class="rounded-circle bg-success d-flex align-items-center justify-content-center me-3" 
                                     style="width: 50px; height: 50px;">
                                    <i class="bi bi-person-plus text-white"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">Add Publisher</div>
                                    <small class="text-muted">Register new publisher</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="generate_endpoint.php" class="text-decoration-none">
                            <div class="d-flex align-items-center p-3 bg-light rounded-3 h-100 quick-action-card">
                                <div class="rounded-circle bg-info d-flex align-items-center justify-content-center me-3" 
                                     style="width: 50px; height: 50px;">
                                    <i class="bi bi-link-45deg text-white"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">Generate Endpoint</div>
                                    <small class="text-muted">Create traffic endpoint</small>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="reports.php" class="text-decoration-none">
                            <div class="d-flex align-items-center p-3 bg-light rounded-3 h-100 quick-action-card">
                                <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center me-3" 
                                     style="width: 50px; height: 50px;">
                                    <i class="bi bi-bar-chart text-white"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">View Reports</div>
                                    <small class="text-muted">Analytics & insights</small>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.quick-action-card {
    transition: var(--transition);
    border: 1px solid rgba(0,0,0,0.05);
}

.quick-action-card:hover {
    background: white !important;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Loading states */
.chart-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 320px;
    color: #6c757d;
}

/* Custom scrollbar for recent activity */
.list-group {
    max-height: 350px;
    overflow-y: auto;
}

.list-group::-webkit-scrollbar {
    width: 4px;
}

.list-group::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.list-group::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 2px;
}

.list-group::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Initialize Chart
    const ctx = document.getElementById("profitChart").getContext('2d');
    
    const myLineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: "Daily Profit",
                backgroundColor: "rgba(102, 126, 234, 0.1)",
                borderColor: "rgba(102, 126, 234, 1)",
                pointBackgroundColor: "rgba(102, 126, 234, 1)",
                pointBorderColor: "white",
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8,
                data: <?php echo json_encode($chart_data_final); ?>,
                fill: true,
                tension: 0.4
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        callback: function(value, index, values) {
                            return '$' + value.toFixed(2);
                        },
                        color: '#6c757d'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        color: '#6c757d'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleColor: 'white',
                    bodyColor: 'white',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'Profit: $' + context.parsed.y.toFixed(5);
                        }
                    }
                }
            }
        }
    });

    // 2. Auto-refresh functionality
    const refreshSwitch = document.getElementById('autoRefreshSwitch');
    let refreshInterval;
    let countdown = 30;
    let countdownInterval;

    function updateCountdown() {
        const label = refreshSwitch.nextElementSibling;
        if (refreshSwitch.checked) {
            label.innerHTML = `<small>Auto-Refresh (${countdown}s)</small>`;
            countdown--;
            if (countdown < 0) {
                countdown = 30;
                window.location.reload();
            }
        }
    }

    function startRefresh() {
        if (!refreshInterval) {
            countdown = 30;
            countdownInterval = setInterval(updateCountdown, 1000);
            refreshInterval = setInterval(function() {
                window.location.reload();
            }, 30000);
        }
    }

    function stopRefresh() {
        clearInterval(refreshInterval);
        clearInterval(countdownInterval);
        refreshInterval = null;
        countdownInterval = null;
        refreshSwitch.nextElementSibling.innerHTML = '<small>Auto-Refresh (30s)</small>';
    }

    // Load saved state
    if (localStorage.getItem('autoRefreshEnabled') === 'true') {
        refreshSwitch.checked = true;
        startRefresh();
    }

    refreshSwitch.addEventListener('change', function() {
        if (this.checked) {
            localStorage.setItem('autoRefreshEnabled', 'true');
            startRefresh();
        } else {
            localStorage.setItem('autoRefreshEnabled', 'false');
            stopRefresh();
        }
    });

    // 3. Add some animations and interactions
    document.querySelectorAll('.stats-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>