<?php
require_once 'db.php';

// ACTION HANDLER (DELETE PUBLISHER)
if (isset($_GET['action']) && $_GET['action'] === 'delete_publisher' && isset($_GET['id'])) {
    $publisher_id_to_delete = (int)$_GET['id'];
    if ($publisher_id_to_delete > 0) {
        $pdo->beginTransaction();
        try {
            $stmt_sites = $pdo->prepare("DELETE FROM sites WHERE publisher_id = ?");
            $stmt_sites->execute([$publisher_id_to_delete]);
            $stmt_pub = $pdo->prepare("DELETE FROM publishers WHERE id = ?");
            $stmt_pub->execute([$publisher_id_to_delete]);
            $pdo->commit();
            $_SESSION['success_message'] = "Publisher and all associated sites have been deleted successfully.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error deleting publisher: " . $e->getMessage();
        }
    }
    header("Location: publishers.php");
    exit;
}

// Ambil pesan dari session jika ada
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// FORM SUBMISSION (CREATE NEW)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_publisher'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $revenue_share = (float)$_POST['revenue_share'];
    $site_domain = trim($_POST['site_domain']);
    $site_category_id = !empty($_POST['site_category_id']) ? (int)$_POST['site_category_id'] : null;
    $site_domain = preg_replace('/^https?:\/\//', '', $site_domain);
    $site_domain = rtrim($site_domain, '/');
    if (!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($site_domain)) {
        $pdo->beginTransaction();
        try {
            $stmt_pub = $pdo->prepare("INSERT INTO publishers (name, email, revenue_share) VALUES (?, ?, ?)");
            $stmt_pub->execute([$name, $email, $revenue_share]);
            $publisher_id = $pdo->lastInsertId();
            $stmt_site = $pdo->prepare("INSERT INTO sites (publisher_id, domain, category_id, status) VALUES (?, ?, ?, 'active')");
            $stmt_site->execute([$publisher_id, $site_domain, $site_category_id]);
            $pdo->commit();
            $success_message = "Publisher '{$name}' with site '{$site_domain}' created successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->errorInfo[1] == 1062) {
                $error_message = "Error: A publisher with that email or a site with that domain already exists.";
            } else { $error_message = "Database Error: " . $e->getMessage(); }
        }
    } else { $error_message = "Invalid data. Please ensure all fields are filled correctly."; }
}

// Fetch data for display
$publishers = $pdo->query("SELECT * FROM publishers ORDER BY id DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM ad_categories ORDER BY name")->fetchAll();

$page_title = "Publisher Management";
include 'includes/header.php';
?>

<?php if(isset($success_message)): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
<?php if(isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Add New Publisher / Partner</h5>
        <p class="card-text text-muted">Add a new traffic supply partner and their first site here.</p>
    </div>
    <div class="card-body">
        <form method="POST" action="publishers.php">
            <h6 class="text-primary">Publisher Details</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Publisher Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Contact Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
            </div>
             <div class="mb-3">
                <label for="revenue_share" class="form-label">Revenue Share (%)</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="revenue_share" name="revenue_share" min="0" max="100" step="0.1" value="70.0" required>
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-text">The percentage of profit the publisher receives.</div>
            </div>

            <hr class="my-4">
            <h6 class="text-primary">First Site Details</h6>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="site_domain" class="form-label">Site Domain</label>
                    <input type="text" class="form-control" id="site_domain" name="site_domain" placeholder="example.com" required>
                </div>
                 <div class="col-md-6 mb-3">
                    <label for="site_category_id" class="form-label">Site Category</label>
                    <select class="form-select" id="site_category_id" name="site_category_id">
                        <option value="">-- Choose Category --</option>
                        <?php foreach($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <button type="submit" name="create_publisher" class="btn btn-primary mt-3">Add Publisher and Site</button>
        </form>
    </div>
</div>


<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title">Existing Publishers</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Revenue Share</th>
                        <th>Registered On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($publishers)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No publishers found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($publishers as $publisher): ?>
                            <tr>
                                <td><?php echo $publisher['id']; ?></td>
                                <td><?php echo htmlspecialchars($publisher['name']); ?></td>
                                <td><?php echo htmlspecialchars($publisher['email']); ?></td>
                                <td><?php echo $publisher['revenue_share']; ?>%</td>
                                <td><?php echo date('d M Y', strtotime($publisher['created_at'])); ?></td>
                                <td>
                                    <a href="edit_publisher.php?id=<?php echo $publisher['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <a href="?action=delete_publisher&id=<?php echo $publisher['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this publisher and all their sites? This action cannot be undone.');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>