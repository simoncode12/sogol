<?php
require_once 'db.php';

// Cek ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: categories.php");
    exit;
}
$id = (int)$_GET['id'];

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $name = trim($_POST['name']);
    $category_id_to_update = (int)$_POST['id'];

    if (!empty($name) && $category_id_to_update === $id) {
        try {
            $stmt = $pdo->prepare("UPDATE ad_categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            $_SESSION['success_message'] = "Category updated successfully!";
            header("Location: categories.php");
            exit;
        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    } else {
        $error_message = "Category name cannot be empty.";
    }
}

// Fetch data untuk form
$stmt = $pdo->prepare("SELECT * FROM ad_categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();
if (!$category) {
    header("Location: categories.php");
    exit;
}

$page_title = "Edit Category: " . htmlspecialchars($category['name']);
include 'includes/header.php';
?>

<?php if(isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h5 class="card-title"><?php echo $page_title; ?></h5></div>
    <div class="card-body">
        <form method="POST" action="edit_category.php?id=<?php echo $category['id']; ?>">
            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
            <div class="mb-3">
                <label for="name" class="form-label">Category Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($category['name']); ?>" required>
            </div>
            <hr>
            <button type="submit" name="update_category" class="btn btn-primary">Save Changes</button>
            <a href="categories.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>