<?php
require_once 'db.php';

// ACTION HANDLER (PAUSE, ACTIVATE, DELETE)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];

    if ($action === 'delete_campaign') {
        $pdo->beginTransaction();
        try {
            $stmt_formats = $pdo->prepare("DELETE FROM campaign_formats WHERE campaign_id = ?");
            $stmt_formats->execute([$id]);
            $stmt_campaign = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
            $stmt_campaign->execute([$id]);
            $pdo->commit();
            $_SESSION['success_message'] = "Campaign deleted successfully.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error deleting campaign: " . $e->getMessage();
        }
    } elseif ($action === 'pause_campaign') {
        $stmt = $pdo->prepare("UPDATE campaigns SET status = 'paused' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Campaign paused.";
    } elseif ($action === 'activate_campaign') {
        $stmt = $pdo->prepare("UPDATE campaigns SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Campaign activated.";
    }
    header("Location: campaigns.php");
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    $name = trim($_POST['name']);
    $campaign_type = $_POST['campaign_type'];
    $ad_type = $_POST['ad_type'];
    $category_id = (int)$_POST['category_id'];
    $banner_formats = isset($_POST['banner_formats']) ? $_POST['banner_formats'] : [];

    $rtb_endpoint_url = ($campaign_type === 'rtb') ? trim($_POST['rtb_endpoint_url']) : null;
    $ron_adm = ($campaign_type === 'ron') ? trim($_POST['ron_adm']) : null;
    $ron_bid_cpm = ($campaign_type === 'ron' && !empty($_POST['ron_bid_cpm'])) ? (float)$_POST['ron_bid_cpm'] : null;
    
    if (!empty($name) && !empty($category_id)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO campaigns (name, status, campaign_type, ad_type, category_id, rtb_endpoint_url, ron_adm, ron_bid_cpm) 
                 VALUES (?, 'active', ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $campaign_type, $ad_type, $category_id, $rtb_endpoint_url, $ron_adm, $ron_bid_cpm]);
            $campaign_id = $pdo->lastInsertId();

            if ($ad_type === 'banner' && !empty($banner_formats)) {
                $stmt_formats = $pdo->prepare("INSERT INTO campaign_formats (campaign_id, format_id) VALUES (?, ?)");
                foreach ($banner_formats as $format_id) { $stmt_formats->execute([$campaign_id, (int)$format_id]); }
            }
            $pdo->commit();
            $success_message = "Campaign '{$name}' created successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Database Error: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid data. Name and Category are mandatory.";
    }
}

// Fetch data for display
$campaigns_query = "SELECT c.*, cat.name as category_name, GROUP_CONCAT(af.width, 'x', af.height SEPARATOR ', ') as formats FROM campaigns c LEFT JOIN ad_categories cat ON c.category_id = cat.id LEFT JOIN campaign_formats cf ON c.id = cf.campaign_id LEFT JOIN ad_formats af ON cf.format_id = af.id GROUP BY c.id ORDER BY c.id DESC";
$campaigns = $pdo->query($campaigns_query)->fetchAll();
$formats = $pdo->query("SELECT * FROM ad_formats ORDER BY format_name")->fetchAll();
$categories = $pdo->query("SELECT * FROM ad_categories ORDER BY name")->fetchAll();

$page_title = "Campaigns Management";
include 'includes/header.php';
?>

<?php if(isset($success_message)): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
<?php if(isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h5 class="card-title">Create New Campaign</h5></div>
    <div class="card-body">
        <form method="POST" action="campaigns.php">
            <div class="mb-3">
                <label for="name" class="form-label">Campaign Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Campaign Type</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="campaign_type" id="type_rtb" value="rtb" checked>
                        <label class="form-check-label" for="type_rtb">RTB (External Demand)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="campaign_type" id="type_ron" value="ron">
                        <label class="form-check-label" for="type_ron">RON (Internal/Direct Ad)</label>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="ad_type" class="form-label">Ad Format</label>
                    <select class="form-select" id="ad_type" name="ad_type" required>
                        <option value="banner">Banner</option>
                        <option value="vast">Pre-roll / VAST</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="category_id" class="form-label">Category</label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="" disabled selected>-- Select a Category --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <hr>

            <div id="rtb_fields">
                <div class="mb-3">
                    <label for="rtb_endpoint_url" class="form-label">RTB Endpoint URL</label>
                    <input type="url" class="form-control" id="rtb_endpoint_url" name="rtb_endpoint_url" placeholder="URL from your demand partner">
                    <div class="form-text">Harga/payout akan diambil otomatis dari respons real-time partner ini.</div>
                </div>
            </div>

            <div id="ron_fields" style="display: none;">
                <div class="mb-3">
                    <label for="ron_adm" class="form-label">Ad Markup (HTML or VAST XML)</label>
                    <textarea class="form-control" id="ron_adm" name="ron_adm" rows="8"></textarea>
                </div>
                 <div class="mb-3">
                    <label for="ron_bid_cpm" class="form-label">Bid Price CPM (USD)</label>
                    <input type="number" class="form-control" id="ron_bid_cpm" name="ron_bid_cpm" min="0.000001" step="0.000001" placeholder="e.g., 0.10">
                    <div class="form-text">Harga tetap yang akan ditawar kampanye ini.</div>
                </div>
            </div>
            
            <div class="mb-3" id="banner_formats_container">
                <label for="banner_formats" class="form-label">Banner Sizes</label>
                <select class="form-select" id="banner_formats" name="banner_formats[]" multiple size="8">
                     <?php foreach ($formats as $format): ?>
                        <option value="<?php echo $format['id']; ?>"><?php echo htmlspecialchars($format['format_name'] . ' (' . $format['width'] . 'x' . $format['height'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" name="create_campaign" class="btn btn-primary">Create Campaign</button>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h5 class="card-title">Existing Campaigns</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr><th>Name</th><th>Type</th><th>Rate (CPM)</th><th>Format</th><th>Category</th><th>Sizes</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($campaigns)): ?>
                        <tr><td colspan="8" class="text-center">No campaigns found.</td></tr>
                    <?php else: ?>
                        <?php 
                            $adTypeLabels = ['banner' => 'Banner', 'vast' => 'VAST'];
                            foreach ($campaigns as $campaign): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                            <td><span class="badge bg-light text-dark"><?php echo strtoupper($campaign['campaign_type']); ?></span></td>
                            <td class="fw-bold text-primary">
                                <?php if($campaign['campaign_type'] === 'ron'): ?>
                                    $<?php echo number_format((float)$campaign['ron_bid_cpm'], 4); ?>
                                <?php else: ?>
                                    <span class="text-muted">Dynamic</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-info"><?php echo $adTypeLabels[$campaign['ad_type']] ?? ucfirst($campaign['ad_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($campaign['category_name'] ?? 'N/A'); ?></td>
                            <td style="max-width: 200px;"><?php echo htmlspecialchars($campaign['formats'] ?? 'N/A'); ?></td>
                            <td><span class="badge bg-<?php echo $campaign['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($campaign['status']); ?></span></td>
                            <td>
                                <a href="edit_campaign.php?id=<?php echo $campaign['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="?action=<?php echo $campaign['status'] == 'active' ? 'pause_campaign' : 'activate_campaign'; ?>&id=<?php echo $campaign['id']; ?>" class="btn btn-sm btn-<?php echo $campaign['status'] == 'active' ? 'secondary' : 'success'; ?>"><?php echo $campaign['status'] == 'active' ? 'Pause' : 'Activate'; ?></a>
                                <a href="?action=delete_campaign&id=<?php echo $campaign['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</a>
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
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="campaign_type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const isRtb = this.value === 'rtb';
            document.getElementById('rtb_fields').style.display = isRtb ? 'block' : 'none';
            document.getElementById('ron_fields').style.display = isRtb ? 'none' : 'block';
        });
    });
    const adTypeSelect = document.getElementById('ad_type');
    const bannerFormatsContainer = document.getElementById('banner_formats_container');
    function toggleBannerFormats() {
        if (adTypeSelect.value === 'banner') {
            bannerFormatsContainer.style.display = 'block';
        } else {
            bannerFormatsContainer.style.display = 'none';
        }
    }
    toggleBannerFormats();
    adTypeSelect.addEventListener('change', toggleBannerFormats);
});
</script>
<?php include 'includes/footer.php'; ?>