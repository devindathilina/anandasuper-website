<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/../config/file_upload_utils.php';

$message = '';
$message_type = '';

function validateProductData($product_name, $wholesale_price, $retail_price, $unit_id, $qty, $low_stock_threshold): array
{
    $errors = [];

    if (empty($product_name)) {
        $errors[] = 'Product name is required!';
    }
    if (strlen($product_name) < 2) {
        $errors[] = 'Product name must be at least 2 characters long!';
    }
    if (strlen($product_name) > 255) {
        $errors[] = 'Product name cannot exceed 255 characters!';
    }

    if (empty($unit_id)) {
        $errors[] = 'Product unit is required!';
    }

    if (!is_numeric($wholesale_price) || floatval($wholesale_price) < 0) {
        $errors[] = 'Wholesale price must be a positive number!';
    }
    if (!is_numeric($retail_price) || floatval($retail_price) < 0) {
        $errors[] = 'Retail price must be a positive number!';
    }

    if (!is_numeric($qty) || floatval($qty) < 0) {
        $errors[] = 'Quantity must be a positive number!';
    }
    if (!is_numeric($low_stock_threshold) || intval($low_stock_threshold) < 0) {
        $errors[] = 'Low stock threshold must be a non-negative number!';
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function checkProductExists($db, $product_name): array
{
    $stmt = $db->prepare("SELECT product_id FROM products WHERE product_name = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("s", $product_name);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'errors' => ['A product with this name already exists!']];
    }
    $stmt->close();

    return ['success' => true, 'errors' => []];
}

function saveProduct($db, $category_id, $unit_id, $product_name, $description, $product_image, $barcode, $wholesale_price, $retail_price, $qty, $low_stock_threshold, $is_available, $is_active): bool
{
    $stmt = $db->prepare("INSERT INTO products (category_id, unit_id, product_name, description, product_image, barcode, wholesale_price, retail_price, qty, low_stock_threshold, is_available, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("iissssdddiii", $category_id, $unit_id, $product_name, $description, $product_image, $barcode, $wholesale_price, $retail_price, $qty, $low_stock_threshold, $is_available, $is_active);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

$form_product_name = '';
$form_category_id = '';
$form_unit_id = '';
$form_description = '';
$form_barcode = '';
$form_wholesale_price = '0.00';
$form_retail_price = '0.00';
$form_qty = '0.00';
$form_low_stock_threshold = '10';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = trim($_POST['product_name'] ?? '');
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $wholesale_price = trim($_POST['wholesale_price'] ?? '0.00');
    $retail_price = trim($_POST['retail_price'] ?? '0.00');
    $qty = trim($_POST['qty'] ?? '0.00');
    $low_stock_threshold = intval($_POST['low_stock_threshold'] ?? 10);
    $is_available = 1;
    $is_active = 1;

    $form_product_name = $product_name;
    $form_category_id = $category_id;
    $form_unit_id = $unit_id;
    $form_description = $description;
    $form_barcode = $barcode;
    $form_wholesale_price = $wholesale_price;
    $form_retail_price = $retail_price;
    $form_qty = $qty;
    $form_low_stock_threshold = $low_stock_threshold;

    $validation = validateProductData($product_name, $wholesale_price, $retail_price, $unit_id, $qty, $low_stock_threshold);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    $exists = checkProductExists($db, $product_name);
    if (!$exists['success']) {
        $message = implode('<br>', $exists['errors']);
        $message_type = 'alert-danger';
    }

    $product_image = null;
    if (!$message && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = processImageUpload($_FILES['product_image'], [
            'upload_path' => __DIR__ . '/../assets/product/',
            'allowed_types' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_size' => 2 * 1024 * 1024,
            'min_width' => 200,
            'min_height' => 200,
            'max_width' => 800,
            'max_height' => 800,
            'output_size' => 500,
            'crop_square' => true,
            'quality' => 85,
            'prefix' => 'product_',
            'output_format' => 'webp'
        ]);

        if (!$uploadResult['success']) {
            $message = $uploadResult['error'];
            $message_type = 'alert-danger';
        }
        $product_image = $uploadResult['filename'] ?? null;
    }

    if (!$message) {
        $db->begin_transaction();
        try {
            if (!saveProduct($db, $category_id, $unit_id, $product_name, $description, $product_image, $barcode, $wholesale_price, $retail_price, $qty, $low_stock_threshold, $is_available, $is_active)) {
                throw new Exception('Failed to save product.');
            }

            $db->commit();
            $message = 'Product added successfully!';
            $message_type = 'alert-success';

            $form_product_name = '';
            $form_category_id = '';
            $form_unit_id = '';
            $form_description = '';
            $form_barcode = '';
            $form_wholesale_price = '0.00';
            $form_retail_price = '0.00';
            $form_qty = '0.00';
            $form_low_stock_threshold = '10';
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while adding the product. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Add Product";
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
                            <li class="breadcrumb-item"><a href="products.php" class="text-light">Products</a></li>
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Add Product</li>
                        </ol>
                    </nav>

                    <?php if ($message): ?>
                        <div class="alert <?= $message_type ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php if ($message_type === 'alert-success'): ?>
                            <script>
                                setTimeout(() => {
                                    window.location.href = 'products.php';
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
                                        Add Product
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="productForm" enctype="multipart/form-data" novalidate>
                                <?= csrfInputField(); ?>

                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="product_name">Product Name</label>
                                        <input type="text" class="form-control" id="product_name" name="product_name" minlength="2" maxlength="255" required value="<?= htmlspecialchars($form_product_name, ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="text-muted">Enter product name</small>
                                        <div class="invalid-feedback">Please enter product name</div>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label for="category_id">Category</label>
                                        <select id="category_id" name="category_id" class="form-control">
                                            <option value="">Select Category</option>
                                        </select>
                                        <small class="text-muted">Select product category (optional)</small>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label for="unit_id">Unit</label>
                                        <select id="unit_id" name="unit_id" class="form-control" required>
                                            <option value="">Select Unit</option>
                                        </select>
                                        <small class="text-muted">Select product unit</small>
                                        <div class="invalid-feedback">Please select a unit</div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" class="form-control" rows="3" maxlength="1000"><?= htmlspecialchars($form_description, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    <small class="text-muted">Enter product description (optional)</small>
                                </div>

                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="barcode">Barcode</label>
                                        <input type="text" class="form-control" id="barcode" name="barcode" maxlength="100" value="<?= htmlspecialchars($form_barcode, ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="text-muted">Enter barcode (optional)</small>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="product_image">Product Image</label>
                                        <input type="file" id="product_image" name="product_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                        <small class="text-muted">Optional. JPG, PNG, or WEBP. Max 2MB. Recommended 500x500px</small>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="wholesale_price">Wholesale Price</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">Rs.</span>
                                            </div>
                                            <input type="number" class="form-control" id="wholesale_price" name="wholesale_price" step="0.01" min="0" required value="<?= htmlspecialchars($form_wholesale_price, ENT_QUOTES, 'UTF-8'); ?>">
                                            <div class="input-group-append">
                                                <span class="input-group-text">per unit</span>
                                            </div>
                                        </div>
                                        <small class="text-muted">Enter wholesale price</small>
                                        <div class="invalid-feedback">Please enter wholesale price</div>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="retail_price">Retail Price</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">Rs.</span>
                                            </div>
                                            <input type="number" class="form-control" id="retail_price" name="retail_price" step="0.01" min="0" required value="<?= htmlspecialchars($form_retail_price, ENT_QUOTES, 'UTF-8'); ?>">
                                            <div class="input-group-append">
                                                <span class="input-group-text">per unit</span>
                                            </div>
                                        </div>
                                        <small class="text-muted">Enter retail price</small>
                                        <div class="invalid-feedback">Please enter retail price</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="qty">Initial Stock Quantity</label>
                                        <input type="number" class="form-control" id="qty" name="qty" step="0.01" min="0" required value="<?= htmlspecialchars($form_qty, ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="text-muted">Enter initial stock quantity (supports decimals)</small>
                                        <div class="invalid-feedback">Please enter quantity</div>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="low_stock_threshold">Low Stock Threshold</label>
                                        <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold" min="0" required value="<?= htmlspecialchars($form_low_stock_threshold, ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="text-muted">Alert when stock falls below this amount</small>
                                        <div class="invalid-feedback">Please enter threshold value</div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus mr-1"></i> Add Product
                                </button>
                                <a href="products.php" class="btn btn-secondary mt-3">Cancel</a>
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
            var currentCategoryId = <?= $form_category_id ? htmlspecialchars($form_category_id, ENT_QUOTES, 'UTF-8') : '0' ?>;
            var currentUnitId = <?= $form_unit_id ? htmlspecialchars($form_unit_id, ENT_QUOTES, 'UTF-8') : '0' ?>;

            $('#category_id').select2({
                placeholder: 'Select Category',
                allowClear: true,
                ajax: {
                    url: 'api/product/fetch_product_category_2.php',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) { return { search: params.term }; },
                    processResults: function(data) {
                        return data.success ? { results: data.categories.map(c => ({ id: c.category_id, text: c.category_name })) } : { results: [] };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });

            if (currentCategoryId > 0) {
                var option = new Option('Loading...', currentCategoryId, true, true);
                $('#category_id').append(option).trigger('change');
                $.ajax({
                    url: 'api/product/fetch_product_category_2.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { category_id: currentCategoryId },
                    success: function(response) {
                        if (response.success && response.categories.length > 0) {
                            var cat = response.categories[0];
                            $('#category_id').empty().append(new Option(cat.category_name, cat.category_id, true, true)).trigger('change');
                        } else {
                            $('#category_id').empty().trigger('change');
                        }
                    },
                    error: function() { $('#category_id').empty().trigger('change'); }
                });
            }

            $('#unit_id').select2({
                placeholder: 'Select Unit',
                allowClear: false,
                ajax: {
                    url: 'api/product/fetch_product_unit_2.php',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) { return { search: params.term }; },
                    processResults: function(data) {
                        return data.success ? { results: data.units.map(u => ({ id: u.unit_id, text: u.unit_code })) } : { results: [] };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });

            if (currentUnitId > 0) {
                var option = new Option('Loading...', currentUnitId, true, true);
                $('#unit_id').append(option).trigger('change');
                $.ajax({
                    url: 'api/product/fetch_product_unit_2.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { unit_id: currentUnitId },
                    success: function(response) {
                        if (response.success && response.units.length > 0) {
                            var unit = response.units[0];
                            $('#unit_id').empty().append(new Option(unit.unit_code, unit.unit_id, true, true)).trigger('change');
                        } else {
                            $('#unit_id').empty().trigger('change');
                        }
                    },
                    error: function() { $('#unit_id').empty().trigger('change'); }
                });
            }

            $('#productForm').on('submit', function(e) {
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
