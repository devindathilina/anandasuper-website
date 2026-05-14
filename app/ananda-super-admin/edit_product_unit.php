<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$message = '';
$message_type = '';

function getUnitById($db, $unit_id): array
{
    $stmt = $db->prepare("SELECT unit_id, unit_code, is_active FROM product_units WHERE unit_id = ?");
    if (!$stmt || !$stmt->bind_param("i", $unit_id) || !$stmt->execute()) {
        return ['success' => false, 'data' => null];
    }
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'data' => null];
    }
    $data = $result->fetch_assoc();
    $stmt->close();

    return ['success' => true, 'data' => $data];
}

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

function checkUnitExistsForUpdate($db, $unit_code, $unit_id): array
{
    $stmt = $db->prepare("SELECT unit_id FROM product_units WHERE unit_code = ? AND unit_id != ? LIMIT 1");
    if (!$stmt || !$stmt->bind_param("si", $unit_code, $unit_id) || !$stmt->execute()) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    if ($exists) {
        return ['success' => false, 'errors' => ['A unit with this code already exists!']];
    }
    return ['success' => true, 'errors' => []];
}

function updateUnit($db, $unit_id, $unit_code, $is_active): bool
{
    $stmt = $db->prepare("UPDATE product_units SET unit_code = ?, is_active = ? WHERE unit_id = ?");
    if (!$stmt || !$stmt->bind_param("sii", $unit_code, $is_active, $unit_id) || !$stmt->execute()) {
        return false;
    }
    $stmt->close();

    return true;
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: product_units.php');
    exit;
}

$unit_id = (int)$_GET['id'];
$unitResult = getUnitById($db, $unit_id);
if (!$unitResult['success']) {
    header('Location: product_units.php');
    exit;
}

$unit_data = $unitResult['data'];
$unit_code = $unit_data['unit_code'];
$is_active = $unit_data['is_active'];

$form_unit_code = $unit_code;
$form_is_active = $is_active;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $unit_code = trim($_POST['unit_code']);
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    $form_unit_code = $unit_code;
    $form_is_active = $is_active;

    $validation = validateUnitData($unit_code);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    if (!$message) {
        $exists = checkUnitExistsForUpdate($db, $unit_code, $unit_id);
        if (!$exists['success']) {
            $message = implode('<br>', $exists['errors']);
            $message_type = 'alert-danger';
        }
    }

    if (!$message) {
        $db->begin_transaction();
        try {
            if (!updateUnit($db, $unit_id, $unit_code, $is_active)) {
                throw new Exception('Failed to update product unit.');
            }

            $db->commit();
            $message = 'Product unit updated successfully!';
            $message_type = 'alert-success';

            $unit_code = $unit_code;
            $is_active = $is_active;
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while updating the product unit. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Edit Product Unit";
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Edit Product Unit</li>
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
                                        Edit Product Unit
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

                                    <div class="form-group col-md-6">
                                        <label for="is_active">Status</label>
                                        <select class="form-control" id="is_active" name="is_active">
                                            <option value="1" <?= $form_is_active == 1 ? 'selected' : '' ?>>Active</option>
                                            <option value="0" <?= $form_is_active == 0 ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                        <small class="text-muted">Set unit status</small>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <strong>Common Unit Codes:</strong> ml (milliliter), l (liter), g (gram), kg (kilogram), pcs (pieces), m (meter), cm (centimeter)
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-save mr-1"></i> Update Product Unit
                                </button>
                                <a href="product_units.php" class="btn btn-secondary mt-3">Cancel</a>
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