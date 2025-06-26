<?php
require_once 'db.php';

// Cek apakah ID ada di URL dan valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid publisher ID.";
    header("Location: publishers.php");
    exit;
}
$id = (int)$_GET['id'];


// ==================================================================
// HANDLE FORM SUBMISSION (UPDATE DATA)
// ==================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_publisher'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $revenue_share = (float)$_POST['revenue_share'];
    $publisher_id_to_update = (int)$_POST['id'];

    // Validasi data
    if (!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && $publisher_id_to_update === $id) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE publishers SET name = ?, email = ?, revenue_share = ? WHERE id = ?"
            );
            $stmt->execute([$name, $email, $revenue_share, $id]);
            
            $_SESSION['success_message'] = "Publisher '{$name}' updated successfully!";
            header("Location: publishers.php");
            exit;

        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid data submitted.";
    }
}


// ==================================================================
// FETCH EXISTING DATA FOR THE FORM
// ==================================================================
$stmt = $pdo->prepare("SELECT * FROM publishers WHERE id = ?");
$stmt->execute([$id]);
$publisher = $stmt->fetch();

// Jika publisher dengan ID tersebut tidak ditemukan, kembalikan ke halaman daftar
if (!$publisher) {
    $_SESSION['error_message'] = "Publisher with ID {$id} not found.";
    header("Location: publishers.php");
    exit;
}


$page_title = "Edit Publisher: " . htmlspecialchars($publisher['name']);
include 'includes/header.php';
?>

<?php if(isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title"><?php echo $page_title; ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" action="edit_publisher.php?id=<?php echo $publisher['id']; ?>">
            <input type="hidden" name="id" value="<?php echo $publisher['id']; ?>">

            <div class="mb-3">
                <label for="name" class="form-label">Publisher Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($publisher['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Contact Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($publisher['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="revenue_share" class="form-label">Revenue Share (%)</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="revenue_share" name="revenue_share" min="0" max="100" step="0.1" value="<?php echo $publisher['revenue_share']; ?>" required>
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-text">The percentage of profit the publisher receives.</div>
            </div>
            
            <hr>
            <button type="submit" name="update_publisher" class="btn btn-primary">Save Changes</button>
            <a href="publishers.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>


<?php include 'includes/footer.php'; ?>