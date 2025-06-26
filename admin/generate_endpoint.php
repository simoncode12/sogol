<?php
require_once 'db.php';

// ACTION HANDLER (PAUSE, ACTIVATE, DELETE)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];

    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM rtb_endpoints_generated WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Endpoint deleted successfully.";
    } elseif ($action === 'pause') {
        $stmt = $pdo->prepare("UPDATE rtb_endpoints_generated SET status = 'paused' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Endpoint paused.";
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE rtb_endpoints_generated SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Endpoint activated.";
    }
    header("Location: generate_endpoint.php");
    exit;
}

// Ambil pesan dari session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// FORM SUBMISSION (CREATE NEW)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_endpoint'])) {
    $name = trim($_POST['name']);
    $publisher_id = !empty($_POST['publisher_id']) ? (int)$_POST['publisher_id'] : null;
    $site_id = !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null;
    $ad_format = $_POST['ad_format'];
    $bid_price_is_cpm = isset($_POST['bid_price_is_cpm']) ? 1 : 0; // Ambil nilai checkbox

    if (!empty($name) && !empty($ad_format) && $publisher_id > 0 && $site_id > 0) {
        $endpoint_hash = md5(uniqid($name, true));
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO rtb_endpoints_generated (name, endpoint_hash, publisher_id, site_id, ad_format, status, bid_price_is_cpm) VALUES (?, ?, ?, ?, ?, 'active', ?)"
            );
            $stmt->execute([$name, $endpoint_hash, $publisher_id, $site_id, $ad_format, $bid_price_is_cpm]);
            $success_message = "Endpoint '{$name}' created successfully!";
        } catch (PDOException $e) { $error_message = "Database Error: " . $e->getMessage(); }
    } else { $error_message = "All fields are required."; }
}

// Fetch data for display
$endpoints = $pdo->query("SELECT e.*, p.name as publisher_name, s.domain as site_domain FROM rtb_endpoints_generated e LEFT JOIN publishers p ON e.publisher_id = p.id LEFT JOIN sites s ON e.site_id = s.id ORDER BY e.id DESC")->fetchAll();
$publishers = $pdo->query("SELECT id, name FROM publishers ORDER BY name")->fetchAll();
$all_sites_stmt = $pdo->query("SELECT id, publisher_id, domain FROM sites WHERE status = 'active' ORDER BY domain");
$sites_by_publisher = [];
while ($row = $all_sites_stmt->fetch(PDO::FETCH_ASSOC)) {
    $sites_by_publisher[$row['publisher_id']][] = $row;
}

$page_title = "Sell Traffic (Supply Endpoints)";
include 'includes/header.php';
?>

<?php if(isset($success_message)): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
<?php if(isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h5 class="card-title">Create New Endpoint for Partners</h5></div>
    <div class="card-body">
        <form method="POST" action="generate_endpoint.php">
            <div class="mb-3">
                <label for="name" class="form-label">Endpoint Name</label>
                <input type="text" class="form-control" id="name" name="name" placeholder="e.g., Adserver.online Banner Traffic" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="publisher_id" class="form-label">Traffic Partner (Publisher)</label>
                    <select class="form-select" id="publisher_id" name="publisher_id" required>
                        <option value="">-- Select Partner --</option>
                        <?php foreach ($publishers as $publisher): ?>
                            <option value="<?php echo $publisher['id']; ?>"><?php echo htmlspecialchars($publisher['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="site_id" class="form-label">Site</label>
                    <select class="form-select" id="site_id" name="site_id" required disabled>
                        <option value="">-- Select Partner First --</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="ad_format" class="form-label">Ad Format</label>
                <select class="form-select" id="ad_format" name="ad_format" required>
                    <option value="banner">Banner</option>
                    <option value="vast">Pre-roll / VAST</option>
                </select>
            </div>
            <hr>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="bid_price_is_cpm" id="bid_price_is_cpm" value="1">
                <label class="form-check-label" for="bid_price_is_cpm">
                    Send Bid Price as CPM Value
                </label>
                <div class="form-text text-muted">Activate this for partners who expect the 'price' field in the bid response to be a CPM rate, not a per-impression price.</div>
            </div>
            <button type="submit" name="create_endpoint" class="btn btn-primary">Generate Endpoint URL</button>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h5 class="card-title">Generated Endpoints</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>Endpoint URL</th>
                        <th>Partner/Publisher</th>
                        <th>Price Format</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($endpoints)): ?>
                        <tr><td colspan="6" class="text-center">No endpoints created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($endpoints as $endpoint): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($endpoint['name']); ?></td>
                            <td><code>.../rtb.php?ep=<?php echo $endpoint['endpoint_hash']; ?></code></td>
                            <td><?php echo htmlspecialchars($endpoint['publisher_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($endpoint['bid_price_is_cpm'] == 1): ?>
                                    <span class="badge bg-success">CPM</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Per-Impression</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-<?php echo $endpoint['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($endpoint['status']); ?></span></td>
                            <td>
                                <a href="edit_endpoint.php?id=<?php echo $endpoint['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="?action=<?php echo $endpoint['status'] == 'active' ? 'pause' : 'activate'; ?>&id=<?php echo $endpoint['id']; ?>" class="btn btn-sm btn-<?php echo $endpoint['status'] == 'active' ? 'secondary' : 'success'; ?>"><?php echo $endpoint['status'] == 'active' ? 'Pause' : 'Activate'; ?></a>
                                <a href="?action=delete&id=<?php echo $endpoint['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sitesByPublisher = <?php echo json_encode($sites_by_publisher); ?>;
    const publisherSelect = document.getElementById('publisher_id');
    const siteSelect = document.getElementById('site_id');
    publisherSelect.addEventListener('change', function () {
        const selectedPublisherId = this.value;
        siteSelect.innerHTML = '<option value="">-- Select Site --</option>';
        if (!selectedPublisherId) {
            siteSelect.disabled = true;
            siteSelect.innerHTML = '<option value="">-- Select Partner First --</option>';
            return;
        }
        siteSelect.disabled = false;
        const sites = sitesByPublisher[selectedPublisherId] || [];
        if (sites.length === 0) {
             siteSelect.innerHTML = '<option value="">-- No active sites for this partner --</option>';
             return;
        }
        sites.forEach(function (site) {
            const option = document.createElement('option');
            option.value = site.id;
            option.textContent = site.domain;
            siteSelect.appendChild(option);
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>