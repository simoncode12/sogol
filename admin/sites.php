<?php
require_once 'db.php';

// FORM SUBMISSION (CREATE NEW SITE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_site'])) {
    $publisher_id = (int)$_POST['publisher_id'];
    $domain = trim($_POST['domain']);
    $domain = preg_replace('/^https?:\/\//', '', $domain);
    $domain = rtrim($domain, '/');
    $status = $_POST['status'];
    // PERUBAHAN: Ambil category_id
    $category_id = (int)$_POST['category_id'];

    // PERUBAHAN: Validasi sekarang memeriksa category_id
    if ($publisher_id > 0 && !empty($domain) && $category_id > 0) {
        try {
            // PERUBAHAN: Query INSERT sekarang menyertakan category_id
            $stmt = $pdo->prepare("INSERT INTO sites (publisher_id, domain, status, category_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$publisher_id, $domain, $status, $category_id]);
            $success_message = "Site '{$domain}' added successfully!";
        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid data. Publisher, Domain, and Category are required.";
    }
}


// Fetch data for display
// PERUBAHAN: Query sekarang mengambil nama kategori juga
$sites_query = "
    SELECT s.*, p.name as publisher_name, cat.name as category_name
    FROM sites s
    JOIN publishers p ON s.publisher_id = p.id
    LEFT JOIN ad_categories cat ON s.category_id = cat.id
    ORDER BY s.id DESC
";
$sites = $pdo->query($sites_query)->fetchAll();
$publishers = $pdo->query("SELECT id, name FROM publishers ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM ad_categories ORDER BY name")->fetchAll();

$page_title = "Site Management";
include 'includes/header.php';
?>

<?php if(isset($success_message)): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
<?php if(isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Add New Site</h5></div>
            <div class="card-body">
                <form method="POST" action="sites.php">
                    <div class="mb-3">
                        <label for="publisher_id" class="form-label">Publisher</label>
                        <select class="form-select" id="publisher_id" name="publisher_id" required>
                            <option value="">-- Select Publisher --</option>
                            <?php foreach ($publishers as $publisher): ?>
                                <option value="<?php echo $publisher['id']; ?>"><?php echo htmlspecialchars($publisher['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="domain" class="form-label">Domain</label>
                        <input type="text" class="form-control" id="domain" name="domain" placeholder="example.com" required>
                    </div>
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Site Category</label>
                        <select class="form-select" name="category_id" id="category_id" required>
                            <option value="" disabled selected>-- Select a Category --</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" name="status" id="status" required>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <button type="submit" name="create_site" class="btn btn-primary">Add Site</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Existing Sites</h5></div>
            <div class="card-body">
                 <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Domain</th>
                                <th>Publisher</th>
                                <th>Category</th> <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $status_badges = [
                                'active' => 'bg-success',
                                'pending' => 'bg-warning text-dark',
                                'rejected' => 'bg-danger',
                            ];
                            ?>
                            <?php foreach ($sites as $site): ?>
                            <tr>
                                <td><?php echo $site['id']; ?></td>
                                <td><a href="http://<?php echo htmlspecialchars($site['domain']); ?>" target="_blank"><?php echo htmlspecialchars($site['domain']); ?></a></td>
                                <td><?php echo htmlspecialchars($site['publisher_name']); ?></td>
                                <td><?php echo htmlspecialchars($site['category_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge <?php echo $status_badges[$site['status']]; ?>">
                                        <?php echo ucfirst($site['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="#" class="btn btn-sm btn-info disabled" title="Coming Soon">Edit</a>
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

<?php include 'includes/footer.php'; ?>