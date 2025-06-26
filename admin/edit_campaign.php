<?php
require_once 'db.php';

// Cek apakah ID ada di URL dan valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid campaign ID.";
    header("Location: campaigns.php");
    exit;
}
$id = (int)$_GET['id'];

// HANDLE FORM SUBMISSION (UPDATE DATA)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_campaign'])) {
    $name = trim($_POST['name']);
    $campaign_type = $_POST['campaign_type']; // Ambil tipe dari hidden input
    $campaign_id_to_update = (int)$_POST['id'];

    // Siapkan variabel untuk diupdate
    $rtb_endpoint_url = null;
    $ron_adm = null;
    $ron_bid_cpm = null;

    if ($campaign_type === 'rtb') {
        $rtb_endpoint_url = trim($_POST['rtb_endpoint_url']);
    } elseif ($campaign_type === 'ron') {
        $ron_adm = trim($_POST['ron_adm']);
        $ron_bid_cpm = (float)$_POST['ron_bid_cpm'];
    }

    if (!empty($name) && $campaign_id_to_update === $id) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE campaigns SET name = ?, rtb_endpoint_url = ?, ron_adm = ?, ron_bid_cpm = ? WHERE id = ?"
            );
            $stmt->execute([$name, $rtb_endpoint_url, $ron_adm, $ron_bid_cpm, $id]);
            
            $_SESSION['success_message'] = "Campaign '{$name}' updated successfully!";
            header("Location: campaigns.php");
            exit;

        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid data submitted. Name is required.";
    }
}

// FETCH EXISTING DATA FOR THE FORM
$stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
$stmt->execute([$id]);
$campaign = $stmt->fetch();

// Jika kampanye dengan ID tersebut tidak ditemukan, kembalikan ke halaman daftar
if (!$campaign) {
    $_SESSION['error_message'] = "Campaign with ID {$id} not found.";
    header("Location: campaigns.php");
    exit;
}

$page_title = "Edit Campaign: " . htmlspecialchars($campaign['name']);
$adTypeLabels = ['banner' => 'Banner', 'vast' => 'VAST'];

include 'includes/header.php';
?>

<?php if(isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title"><?php echo $page_title; ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" action="edit_campaign.php?id=<?php echo $campaign['id']; ?>">
            <input type="hidden" name="id" value="<?php echo $campaign['id']; ?>">
            <input type="hidden" name="campaign_type" value="<?php echo $campaign['campaign_type']; ?>">

            <div class="mb-3">
                <label for="name" class="form-label">Campaign Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($campaign['name']); ?>" required>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Campaign Type:</strong> 
                    <span class="badge bg-light text-dark fs-6"><?php echo strtoupper($campaign['campaign_type']); ?></span>
                </div>
                <div class="col-md-6">
                    <strong>Ad Format:</strong> 
                    <span class="badge bg-primary fs-6"><?php echo $adTypeLabels[$campaign['ad_type']] ?? 'Unknown'; ?></span>
                </div>
            </div>
            <hr>
            
            <?php if ($campaign['campaign_type'] === 'rtb'): ?>
                
                <div id="rtb_fields">
                    <div class="mb-3">
                        <label for="rtb_endpoint_url" class="form-label">RTB Endpoint URL</label>
                        <input type="url" class="form-control" id="rtb_endpoint_url" name="rtb_endpoint_url" value="<?php echo htmlspecialchars($campaign['rtb_endpoint_url']); ?>" placeholder="URL from your demand partner">
                    </div>
                </div>

            <?php elseif ($campaign['campaign_type'] === 'ron'): ?>

                <div id="ron_fields">
                    <div class="mb-3">
                        <label for="ron_adm" class="form-label">Ad Markup (HTML or VAST XML)</label>
                        <textarea class="form-control" id="ron_adm" name="ron_adm" rows="8"><?php echo htmlspecialchars($campaign['ron_adm']); ?></textarea>
                    </div>
                     <div class="mb-3">
                        <label for="ron_bid_cpm" class="form-label">Bid Price CPM (USD)</label>
                        <input type="number" class="form-control" id="ron_bid_cpm" name="ron_bid_cpm" min="0.000001" step="0.000001" value="<?php echo htmlspecialchars($campaign['ron_bid_cpm']); ?>" placeholder="e.g., 0.10">
                        <div class="form-text">Harga tetap yang akan ditawar kampanye ini.</div>
                    </div>
                </div>

            <?php endif; ?>

            <hr>
            <button type="submit" name="update_campaign" class="btn btn-primary">Save Changes</button>
            <a href="campaigns.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>