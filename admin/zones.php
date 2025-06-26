<?php
require_once 'db.php';

// ACTION HANDLER (PAUSE, ACTIVATE, DELETE)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];

    if ($action === 'delete_zone') {
        $stmt = $pdo->prepare("DELETE FROM zones WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Zone deleted successfully.";
    } elseif ($action === 'pause_zone') {
        $stmt = $pdo->prepare("UPDATE zones SET status = 'paused' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Zone paused.";
    } elseif ($action === 'activate_zone') {
        $stmt = $pdo->prepare("UPDATE zones SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Zone activated.";
    }
    header("Location: zones.php");
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

// =================================================================================
// FORM SUBMISSION (CREATE NEW) - DIPERBAIKI
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_zone'])) {
    $name = trim($_POST['name']);
    $site_id = (int)$_POST['site_id'];
    $ad_type = $_POST['ad_type'];
    $format_id = null;

    if (!empty($name) && $site_id > 0) {
        try {
            // **PERBAIKAN**: Tentukan format_id berdasarkan ad_type
            if ($ad_type === 'banner') {
                if (!empty($_POST['format_id'])) {
                    $format_id = (int)$_POST['format_id'];
                } else {
                    throw new Exception("Banner Size is required for banner ad type.");
                }
            } elseif ($ad_type === 'vast') {
                // Cari format_id untuk 'VAST Video' yang telah kita buat
                $stmt_vast_format = $pdo->query("SELECT id FROM ad_formats WHERE format_name = 'VAST Video' LIMIT 1");
                $vast_format = $stmt_vast_format->fetch();
                if ($vast_format) {
                    $format_id = $vast_format['id'];
                } else {
                    // Fallback jika belum ada di DB, meskipun seharusnya sudah ada dari Langkah 1
                    throw new Exception("VAST Video format not found in database. Please run the setup SQL.");
                }
            }

            if (is_null($format_id)) {
                 throw new Exception("Could not determine a valid format ID for the zone.");
            }

            $stmt = $pdo->prepare("INSERT INTO zones (name, site_id, ad_type, format_id, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $site_id, $ad_type, $format_id]);
            $success_message = "Zone '{$name}' created successfully!";
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    } else {
        $error_message = "Zone Name and Site are required.";
    }
}


// Fetch data for display
$zones_query = "SELECT z.*, s.domain, f.width, f.height, f.format_name FROM zones z JOIN sites s ON z.site_id = s.id LEFT JOIN ad_formats f ON z.format_id = f.id ORDER BY z.id DESC";
$zones = $pdo->query($zones_query)->fetchAll();
$sites = $pdo->query("SELECT id, domain FROM sites WHERE status = 'active' ORDER BY domain")->fetchAll();
$ad_formats = $pdo->query("SELECT id, format_name, width, height FROM ad_formats ORDER BY format_name")->fetchAll();

$page_title = "Zone Management";
include 'includes/header.php';
?>

<?php if(isset($success_message)): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
<?php if(isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Create New Zone</h5></div>
            <div class="card-body">
                <form method="POST" action="zones.php">
                    <div class="mb-3">
                        <label for="name" class="form-label">Zone Name</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="e.g., Homepage 300x250" required>
                    </div>
                    <div class="mb-3">
                        <label for="site_id" class="form-label">Site</label>
                        <select class="form-select" id="site_id" name="site_id" required>
                            <option value="" disabled selected>-- Select a Site --</option>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?php echo $site['id']; ?>"><?php echo htmlspecialchars($site['domain']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="ad_type" class="form-label">Ad Type</label>
                        <select class="form-select" id="ad_type" name="ad_type" required>
                            <option value="banner">Banner</option>
                            <option value="vast">VAST (Video)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="format_container">
                        <label for="format_id" class="form-label">Banner Size</label>
                        <select class="form-select" id="format_id" name="format_id">
                            <option value="">-- Select a Size --</option>
                            <?php foreach ($ad_formats as $format): ?>
                                <?php if($format['format_name'] !== 'VAST Video'): ?>
                                <option value="<?php echo $format['id']; ?>"><?php echo htmlspecialchars($format['format_name'] . " ({$format['width']}x{$format['height']})"); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="create_zone" class="btn btn-primary">Create Zone</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Existing Zones</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Site</th>
                                <th>Type / Size</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($zones as $zone): ?>
                            <tr>
                                <td><?php echo $zone['id']; ?></td>
                                <td><?php echo htmlspecialchars($zone['name']); ?></td>
                                <td><?php echo htmlspecialchars($zone['domain']); ?></td>
                                <td>
                                    <?php if($zone['ad_type'] == 'banner'): ?>
                                        <span class="badge bg-info">Banner</span> <?php echo "{$zone['width']}x{$zone['height']}"; ?>
                                    <?php else: ?>
                                        <span class="badge bg-danger">VAST</span> (<?php echo $zone['format_name']; ?>)
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?php echo $zone['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($zone['status']); ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary get-ad-tag-btn" 
                                            data-bs-toggle="modal" data-bs-target="#adTagModal"
                                            data-zone-id="<?php echo $zone['id']; ?>"
                                            data-ad-type="<?php echo $zone['ad_type']; ?>"
                                            data-width="<?php echo $zone['width']; ?>"
                                            data-height="<?php echo $zone['height']; ?>">
                                        Get Tag
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adTagModal" tabindex="-1" aria-labelledby="adTagModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="adTagModalLabel">Get Ad Tag</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="adTagInstructions">Copy and paste this code into your website's HTML.</p>
        <textarea id="adTagCode" class="form-control" rows="6" readonly></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="copyTagBtn">Copy Code</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const adTypeSelect = document.getElementById('ad_type');
    const formatContainer = document.getElementById('format_container');
    const formatSelect = document.getElementById('format_id');

    function toggleFormatField() {
        if (adTypeSelect.value === 'banner') {
            formatContainer.style.display = 'block';
            formatSelect.required = true;
        } else {
            formatContainer.style.display = 'none';
            formatSelect.required = false;
        }
    }
    toggleFormatField();
    adTypeSelect.addEventListener('change', toggleFormatField);

    const adTagModal = document.getElementById('adTagModal');
    const adTagCode = document.getElementById('adTagCode');
    const copyTagBtn = document.getElementById('copyTagBtn');
    const adTagInstructions = document.getElementById('adTagInstructions');

    adTagModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const zoneId = button.getAttribute('data-zone-id');
        const adType = button.getAttribute('data-ad-type');
        const width = button.getAttribute('data-width');
        const height = button.getAttribute('data-height');

        let tag = '';
        const adServerBaseUrl = 'https://adstart.click'; 

        if (adType === 'banner') {
            adTagInstructions.textContent = "Copy and paste this code into your website's HTML where you want the ad to appear.";
            adTagCode.rows = 4;
            tag = `<div id="adstart-zone-${zoneId}" style="width:${width}px; height:${height}px;"></div>\n` +
                  `<script async src="${adServerBaseUrl}/ad_tag.js?zone_id=${zoneId}"><\/script>`;
        } else if (adType === 'vast') {
            adTagInstructions.textContent = "Copy this VAST Tag URL and paste it into your video player's ad tag setting.";
            adTagCode.rows = 2;
            tag = `${adServerBaseUrl}/rtb.php?zone_id=${zoneId}`;
        }
        adTagCode.value = tag;
        copyTagBtn.textContent = 'Copy Code';
    });

    copyTagBtn.addEventListener('click', function() {
        adTagCode.select();
        document.execCommand('copy');
        this.textContent = 'Copied!';
        setTimeout(() => { this.textContent = 'Copy Code'; }, 2000);
    });
});
</script>

<?php include 'includes/footer.php'; ?>