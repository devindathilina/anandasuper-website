<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$message = '';
$message_type = '';

function getProductCategoryById($db, $category_id): array
{
    $stmt = $db->prepare("SELECT category_name, is_active FROM product_category WHERE category_id = ?");
    if (!$stmt) {
        return ['success' => false, 'data' => null];
    }

    $stmt->bind_param("i", $category_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'data' => null];
    }

    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'data' => null];
    }

    $category_name = null;
    $is_active = null;
    $stmt->bind_result($category_name, $is_active);
    $stmt->fetch();
    $stmt->close();

    return ['success' => true, 'data' => ['category_name' => $category_name, 'is_active' => $is_active]];
}

function validateCategoryData($category_name): array
{
    $errors = [];

    if (empty($category_name)) {
        $errors[] = 'Please fill in all required fields!';
    }
    if (strlen($category_name) < 2) {
        $errors[] = 'Category name must be at least 2 characters long!';
    }
    if (strlen($category_name) > 255) {
        $errors[] = 'Category name cannot exceed 255 characters!';
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function checkCategoryExistsForUpdate($db, $category_name, $category_id): array
{
    $stmt = $db->prepare("SELECT category_id FROM product_category WHERE category_name = ? AND category_id != ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("si", $category_name, $category_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'errors' => ['A category with this name already exists!']];
    }
    $stmt->close();

    return ['success' => true, 'errors' => []];
}

function updateCategory($db, $category_name, $category_id): bool
{
    $stmt = $db->prepare("UPDATE product_category SET category_name = ? WHERE category_id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("si", $category_name, $category_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: product_categories.php');
    exit;
}

$category_id = (int)$_GET['id'];

$categoryResult = getProductCategoryById($db, $category_id);
if (!$categoryResult['success']) {
    header('Location: product_categories.php');
    exit;
}

$category_data = $categoryResult['data'];
$is_active = $category_data['is_active'];

$form_category_name = $category_data['category_name'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_category_name = trim($_POST['category_name']);

    $form_category_name = $new_category_name;

    $validation = validateCategoryData($new_category_name);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    $exists = checkCategoryExistsForUpdate($db, $new_category_name, $category_id);
    if (!$exists['success']) {
        $message = implode('<br>', $exists['errors']);
        $message_type = 'alert-danger';
    }

    if (!$message) {
        $db->begin_transaction();
        try {
            if (!updateCategory($db, $new_category_name, $category_id)) {
                throw new Exception('Failed to update product category.');
            }

            $db->commit();
            $message = 'Product category updated successfully!';
            $message_type = 'alert-success';

            $form_category_name = $new_category_name;
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while updating the product category. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Edit Product Category";
?>
<!DOCTYPE html>
<html lang="en">
<?php require_once 'layout/head.php'; ?>

<body id="page-top">
    <div id="wrapper">
        <?php require_once 'layout/sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php require_once 'layout/topbar.php'; ?>
                <div class="container-fluid">
                    <nav aria-label="breadcrumb" class="mb-4">
                        <ol class="breadcrumb bg-primary">
                            <li class="breadcrumb-item"><a href="dashboard.php" class="text-light">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="product_categories.php" class="text-light">Product Categories</a></li>
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Edit Product Category</li>
                        </ol>
                    </nav>

                    <?php if ($message): ?>
                        <div class="alert <?= $message_type ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php if ($message_type === 'alert-success'): ?>
                            <script>
                                setTimeout(() => {
                                    window.location.href = 'product_categories.php';
                                }, 2000);
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-12">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-box mr-2"></i>
                                        Edit Product Category
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="categoryForm" novalidate>
                                <?= csrfInputField(); ?>
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="category_name">Category Name</label>
                                            <input type="text" id="category_name" name="category_name" class="form-control" minlength="2" maxlength="255" required value="<?= htmlspecialchars($form_category_name, ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Enter category name</small>
                                            <div class="invalid-feedback">Please enter category name</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="form-label">Current Status</label>
                                            <div class="form-control-plaintext">
                                                <?php if ($is_active == 1): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                                <small class="form-text text-muted">Status can be changed from the product categories list page using the toggle switch.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-save mr-1"></i> Update Product Category
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php require_once 'layout/footer.php'; ?>
        </div>
    </div>

    <?php require_once 'layout/scripts.php'; ?>
    <script>
        $(function() {
            $('#categoryForm').on('submit', function(e) {
                var form = $(this)[0];

                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                form.classList.add('was-validated');
            });
        });
    </script>
</body>
</html>
