<?php
require_once 'db.php';

// ACTION HANDLER
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM vast_creatives WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "VAST Creative deleted.";
    } elseif ($action === 'pause') {
        $stmt = $pdo->prepare("UPDATE vast_creatives SET status = 'paused' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "VAST Creative paused.";
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE vast_creatives SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "VAST Creative activated.";
    }
    header("Location: vast_creatives.php");
    exit;
}

// FORM SUBMISSION (CREATE/EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $is_url = isset($_POST['is_url']) ? 1 : 0;
    $vast_xml = trim($_POST['vast_xml']);
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    if (!empty($name) && !empty($vast_xml)) {
        try {
            if ($id) { // Update
                $stmt = $pdo->prepare("UPDATE vast_creatives SET name = ?, is_url = ?, vast_xml = ?, cached_vast_xml = NULL, last_fetched = NULL WHERE id = ?");
                $stmt->execute([$name, $is_url, $vast_xml, $id]);
                $_SESSION['success_message'] = "VAST Creative '{$name}' updated successfully!";
            } else { // Create
                $stmt = $pdo->prepare("INSERT INTO vast_creatives (name, is_url, vast_xml, status) VALUES (?, ?, ?, 'active')");
                $stmt->execute([$name, $is_url, $vast_xml]);
                $_SESSION['success_message'] = "VAST Creative '{$name}' created successfully!";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Name and VAST URL/XML cannot be empty.";
    }
    header("Location: vast_creatives.php");
    exit;
}

// Fetch data
$creatives = $pdo->query("SELECT * FROM vast_creatives ORDER BY name ASC")->fetchAll();
$page_title = "VAST Creatives Management";
include 'includes/header.php';

if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success">'.$_SESSION['success_message'].'</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger">'.$_SESSION['error_message'].'</div>';
    unset($_SESSION['error_message']);
}
?>

<div class="card">
    <div class="card-header"><h5 class="card-title">Create/Edit VAST Creative</h5></div>
    <div class="card-body">
        <form method="POST" action="vast_creatives.php">
            <input type="hidden" name="id" id="edit-id">
            <div class="mb-3">
                <label for="name" class="form-label">Creative Name</label>
                <input type="text" class="form-control" id="edit-name" name="name" required>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="edit-is_url" name="is_url" value="1">
                <label class="form-check-label" for="edit-is_url">Is VAST Tag URL?</label>
                <div class="form-text">Check this if you are pasting a URL. Uncheck if you are pasting the full VAST XML content.</div>
            </div>
            <div class="mb-3">
                <label for="vast_xml" class="form-label">VAST URL or XML Content</label>
                <textarea class="form-control" id="edit-vast_xml" name="vast_xml" rows="10" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Creative</button>
            <button type="button" class="btn btn-secondary" onclick="resetForm()">Cancel Edit</button>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h5 class="card-title">Existing VAST Creatives</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr><th>Name</th><th>Type</th><th>Tag/URL</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($creatives)): ?>
                        <tr><td colspan="5" class="text-center">No VAST creatives found.</td></tr>
                    <?php else: foreach ($creatives as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['name']); ?></td>
                            <td><span class="badge bg-<?php echo $c['is_url'] ? 'info' : 'secondary'; ?>"><?php echo $c['is_url'] ? 'URL' : 'Direct XML'; ?></span></td>
                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($c['vast_xml']); ?></td>
                            <td><span class="badge bg-<?php echo $c['status'] == 'active' ? 'success' : 'warning'; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick='editCreative(<?php echo json_encode($c, JSON_HEX_APOS); ?>)'>Edit</button>
                                <a href="?action=<?php echo $c['status'] == 'active' ? 'pause' : 'activate'; ?>&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-info"><?php echo $c['status'] == 'active' ? 'Pause' : 'Activate'; ?></a>
                                <a href="?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function editCreative(creative) {
    document.getElementById('edit-id').value = creative.id;
    document.getElementById('edit-name').value = creative.name;
    document.getElementById('edit-is_url').checked = creative.is_url == 1;
    document.getElementById('edit-vast_xml').value = creative.vast_xml;
    window.scrollTo(0, 0);
}
function resetForm() {
    document.getElementById('edit-id').value = '';
    document.getElementById('edit-name').value = '';
    document.getElementById('edit-is_url').checked = false;
    document.getElementById('edit-vast_xml').value = '';
}
</script>

<?php include 'includes/footer.php'; ?>