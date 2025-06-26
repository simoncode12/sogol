<?php
require_once 'db.php';

// Cek apakah ID ada di URL dan valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid endpoint ID.";
    header("Location: generate_endpoint.php");
    exit;
}
$id = (int)$_GET['id'];


// ==================================================================
// HANDLE FORM SUBMISSION (UPDATE DATA) - DENGAN PERBAIKAN
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_endpoint'])) {
    $name = trim($_POST['name']);
    $publisher_id = (int)$_POST['publisher_id'];
    $site_id = (int)$_POST['site_id'];
    $ad_format = $_POST['ad_format'];
    $endpoint_id_to_update = (int)$_POST['id'];

    // PERBAIKAN: Ambil nilai checkbox. Jika tidak dicentang, nilainya akan 0.
    $bid_price_is_cpm = isset($_POST['bid_price_is_cpm']) ? 1 : 0;

    if (!empty($name) && $publisher_id > 0 && $site_id > 0 && $endpoint_id_to_update === $id) {
        try {
            // PERBAIKAN: Query UPDATE sekarang menyertakan bid_price_is_cpm
            $stmt = $pdo->prepare(
                "UPDATE rtb_endpoints_generated SET name = ?, publisher_id = ?, site_id = ?, ad_format = ?, bid_price_is_cpm = ? WHERE id = ?"
            );
            // PERBAIKAN: Tambahkan variabel baru ke execute()
            $stmt->execute([$name, $publisher_id, $site_id, $ad_format, $bid_price_is_cpm, $id]);
            
            $_SESSION['success_message'] = "Endpoint '{$name}' updated successfully!";
            header("Location: generate_endpoint.php");
            exit;

        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid data submitted.";
    }
}


// FETCH EXISTING DATA FOR THE FORM
$stmt = $pdo->prepare("SELECT * FROM rtb_endpoints_generated WHERE id = ?");
$stmt->execute([$id]);
$endpoint = $stmt->fetch();

if (!$endpoint) {
    $_SESSION['error_message'] = "Endpoint with ID {$id} not found.";
    header("Location: generate_endpoint.php");
    exit;
}

// Fetch data lain untuk dropdown
$publishers = $pdo->query("SELECT id, name FROM publishers ORDER BY name")->fetchAll();
$sites_for_selected_publisher_stmt = $pdo->prepare("SELECT id, domain FROM sites WHERE publisher_id = ? AND status = 'active'");
$sites_for_selected_publisher_stmt->execute([$endpoint['publisher_id']]);
$sites = $sites_for_selected_publisher_stmt->fetchAll();

// Data untuk dropdown dinamis di Javascript
$all_sites_stmt = $pdo->query("SELECT id, publisher_id, domain FROM sites WHERE status = 'active' ORDER BY domain");
$sites_by_publisher = [];
while ($row = $all_sites_stmt->fetch(PDO::FETCH_ASSOC)) {
    $sites_by_publisher[$row['publisher_id']][] = $row;
}

$page_title = "Edit Endpoint: " . htmlspecialchars($endpoint['name']);
include 'includes/header.php';
?>

<?php if(isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h5 class="card-title"><?php echo $page_title; ?></h5></div>
    <div class="card-body">
        <form method="POST" action="edit_endpoint.php?id=<?php echo $endpoint['id']; ?>">
            <input type="hidden" name="id" value="<?php echo $endpoint['id']; ?>">

            <div class="mb-3">
                <label for="name" class="form-label">Endpoint Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($endpoint['name']); ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="publisher_id" class="form-label">Traffic Partner (Publisher)</label>
                    <select class="form-select" id="publisher_id" name="publisher_id" required>
                        <option value="">-- Select Partner --</option>
                        <?php foreach ($publishers as $publisher): ?>
                            <option value="<?php echo $publisher['id']; ?>" <?php if ($publisher['id'] == $endpoint['publisher_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($publisher['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="site_id" class="form-label">Site</label>
                    <select class="form-select" id="site_id" name="site_id" required>
                        <option value="">-- Select Partner First --</option>
                        <?php foreach ($sites as $site): ?>
                             <option value="<?php echo $site['id']; ?>" <?php if ($site['id'] == $endpoint['site_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($site['domain']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="ad_format" class="form-label">Ad Format</label>
                <select class="form-select" id="ad_format" name="ad_format" required>
                    <?php $formats = ['banner', 'vast']; ?>
                    <?php foreach ($formats as $format): ?>
                        <option value="<?php echo $format; ?>" <?php if ($format == $endpoint['ad_format']) echo 'selected'; ?>>
                            <?php echo $format === 'vast' ? 'Pre-roll / VAST' : ucfirst($format); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <hr>
            
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="bid_price_is_cpm" id="bid_price_is_cpm" value="1" <?php if ($endpoint['bid_price_is_cpm'] == 1) echo 'checked'; ?>>
                <label class="form-check-label" for="bid_price_is_cpm">
                    Send Bid Price as CPM Value
                </label>
                <div class="form-text text-muted">Activate this for partners who expect the 'price' field in the bid response to be a CPM rate, not a per-impression price.</div>
            </div>

            <button type="submit" name="update_endpoint" class="btn btn-primary">Save Changes</button>
            <a href="generate_endpoint.php" class="btn btn-secondary">Cancel</a>
        </form>
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
        if (!selectedPublisherId) { return; }
        
        const sites = sitesByPublisher[selectedPublisherId] || [];
        if (sites.length === 0) {
             siteSelect.innerHTML = '<option value="">-- No active sites --</option>';
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