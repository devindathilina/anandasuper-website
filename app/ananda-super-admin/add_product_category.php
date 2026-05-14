<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$message = '';
$message_type = '';

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

function checkCategoryExists($db, $category_name): array
{
    $stmt = $db->prepare("SELECT category_id FROM product_category WHERE category_name = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("s", $category_name);
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

function saveCategory($db, $category_name, $is_active): bool
{
    $stmt = $db->prepare("INSERT INTO product_category (category_name, is_active) VALUES (?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("si", $category_name, $is_active);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

$form_category_name = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category_name = trim($_POST['category_name']);
    $is_active = 1;

    $form_category_name = $category_name;

    $validation = validateCategoryData($category_name);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    $exists = checkCategoryExists($db, $category_name);
    if (!$exists['success']) {
        $message = implode('<br>', $exists['errors']);
        $message_type = 'alert-danger';
    }

    if (!$message) {
        $db->begin_transaction();
        try {
            if (!saveCategory($db, $category_name, $is_active)) {
                throw new Exception('Failed to save product category.');
            }

            $db->commit();
            $message = 'Product category added successfully!';
            $message_type = 'alert-success';

            $form_category_name = '';
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while adding the product category. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Add Product Category";
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Add Product Category</li>
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
                                        Add Product Category
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="categoryForm" novalidate>
                                <?= csrfInputField(); ?>

                                <div class="row">
                                    <div class="form-group col-md-8">
                                        <label for="category_name">Category Name</label>
                                        <input type="text" class="form-control" id="category_name" name="category_name" minlength="2" maxlength="255" required value="<?= htmlspecialchars($form_category_name, ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="text-muted">Enter category name</small>
                                        <div class="invalid-feedback">Please enter category name</div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus mr-1"></i> Add Product Category
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
