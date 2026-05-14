<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$message = '';
$message_type = '';

function validateUnitData($unit_code): array
{
    $errors = [];

    if (empty($unit_code)) {
        $errors[] = 'Please fill in all required fields!';
    }
    if (strlen($unit_code) < 1) {
        $errors[] = 'Unit code must be at least 1 character long!';
    }
    if (strlen($unit_code) > 50) {
        $errors[] = 'Unit code cannot exceed 50 characters!';
    }
    if (!preg_match('/^[a-zA-Z0-9]+$/', $unit_code)) {
        $errors[] = 'Unit code can only contain letters and numbers (e.g., ml, l, g, kg, pcs)!';
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function checkUnitExists($db, $unit_code): array
{
    $stmt = $db->prepare("SELECT unit_id FROM product_units WHERE unit_code = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("s", $unit_code);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'errors' => ['A unit with this code already exists!']];
    }
    $stmt->close();

    return ['success' => true, 'errors' => []];
}

function saveUnit($db, $unit_code, $is_active): bool
{
    $stmt = $db->prepare("INSERT INTO product_units (unit_code, is_active) VALUES (?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("si", $unit_code, $is_active);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

$form_unit_code = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $unit_code = trim($_POST['unit_code']);
    $is_active = 1;

    $form_unit_code = $unit_code;

    $validation = validateUnitData($unit_code);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    $exists = checkUnitExists($db, $unit_code);
    if (!$exists['success']) {
        $message = implode('<br>', $exists['errors']);
        $message_type = 'alert-danger';
    }

    if (!$message) {
        $db->begin_transaction();
        try {
            if (!saveUnit($db, $unit_code, $is_active)) {
                throw new Exception('Failed to save product unit.');
            }

            $db->commit();
            $message = 'Product unit added successfully!';
            $message_type = 'alert-success';

            $form_unit_code = '';
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while adding the product unit. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Add Product Unit";
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
                            <li class="breadcrumb-item"><a href="product_units.php" class="text-light">Product Units</a></li>
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Add Product Unit</li>
                        </ol>
                    </nav>

                    <?php if ($message): ?>
                        <div class="alert <?= $message_type ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php if ($message_type === 'alert-success'): ?>
                            <script>
                                setTimeout(() => {
                                    window.location.href = 'product_units.php';
                                }, 2000);
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-12">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-balance-scale mr-2"></i>
                                        Add Product Unit
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="unitForm" novalidate>
                                <?= csrfInputField(); ?>

                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="unit_code">Unit Code</label>
                                        <input type="text" class="form-control text-uppercase" id="unit_code" name="unit_code" minlength="1" maxlength="50" pattern="[a-zA-Z0-9]+" required value="<?= htmlspecialchars($form_unit_code, ENT_QUOTES, 'UTF-8'); ?>" style="text-transform: uppercase;">
                                        <small class="text-muted">Enter unit code (e.g., ml, l, g, kg, pcs)</small>
                                        <div class="invalid-feedback">Please enter a valid unit code</div>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <strong>Common Unit Codes:</strong> ml (milliliter), l (liter), g (gram), kg (kilogram), pcs (pieces), m (meter), cm (centimeter)
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus mr-1"></i> Add Product Unit
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
            $('#unit_code').on('input', function() {
                this.value = this.value.toUpperCase();
            });

            $('#unitForm').on('submit', function(e) {
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
