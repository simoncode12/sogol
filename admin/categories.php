<?php
require_once 'db.php';

// ACTION HANDLER (DELETE CATEGORY)
if (isset($_GET['action']) && $_GET['action'] === 'delete_category' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Pengecekan keamanan: Pastikan kategori tidak sedang digunakan oleh kampanye manapun
    $stmt_check = $pdo->prepare("SELECT COUNT(*) as count FROM campaigns WHERE category_id = ?");
    $stmt_check->execute([$id]);
    $result = $stmt_check->fetch();

    if ($result['count'] > 0) {
        $_SESSION['error_message'] = "Cannot delete this category because it is currently used by {$result['count']} campaign(s).";
    } else {
        $stmt_delete = $pdo->prepare("DELETE FROM ad_categories WHERE id = ?");
        $stmt_delete->execute([$id]);
        $_SESSION['success_message'] = "Category deleted successfully.";
    }
    
    header("Location: categories.php");
    exit;
}

// FORM SUBMISSION (CREATE NEW)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    $name = trim($_POST['name']);

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO ad_categories (name) VALUES (?)");
            $stmt->execute([$name]);
            $success_message = "Category '{$name}' created successfully!";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Kode error untuk duplicate entry
                $error_message = "Error: Category '{$name}' already exists.";
            } else {
                $error_message = "Database Error: " . $e->getMessage();
            }
        }
    } else {
        $error_message = "Category name cannot be empty.";
    }
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

// Fetch all categories for display
$categories = $pdo->query("SELECT * FROM ad_categories ORDER BY name ASC")->fetchAll();

$page_title = "Category Management";
include 'includes/header.php';
?>

<?php if(isset($success_message)): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
<?php if(isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Add New Category</h5></div>
            <div class="card-body">
                <form method="POST" action="categories.php">
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <button type="submit" name="create_category" class="btn btn-primary">Add Category</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Existing Categories</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo $category['id']; ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td>
                                    <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <a href="?action=delete_category&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this category?');">Delete</a>
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