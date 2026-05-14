<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$admin_info = getAnandaSuperAdminInfo();
if (!$admin_info || ($admin_info['admin_role'] ?? '') !== 'Super Admin') {
    header('Location: dashboard.php');
    exit;
}

$page_name = "Rates";
$message = '';
$message_type = '';
$rate_name = 'bill_payment';

function fetchRateByName($db, $rate_name): ?array
{
    $stmt = $db->prepare("SELECT rate_id, rate_name, rate_value, created_at, updated_at FROM rates WHERE rate_name = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $rate_name);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $rate = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $rate ?: null;
}

function lockRateByName($db, $rate_name): ?array
{
    $stmt = $db->prepare("SELECT rate_id, rate_name, rate_value, created_at, updated_at FROM rates WHERE rate_name = ? LIMIT 1 FOR UPDATE");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $rate_name);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $rate = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $rate ?: null;
}

function validateRateValue($rate_value): array
{
    $errors = [];

    if ($rate_value === '') {
        $errors[] = 'Rate value is required.';
    } elseif (!is_numeric($rate_value)) {
        $errors[] = 'Rate value must be numeric.';
    } else {
        $normalized = number_format((float) $rate_value, 2, '.', '');
        if ((float) $normalized < 0) {
            $errors[] = 'Rate value cannot be negative.';
        }
        if ((float) $normalized > 999.99) {
            $errors[] = 'Rate value must be 999.99 or less.';
        }
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function updateRateValue($db, $rate_id, $rate_value): bool
{
    $stmt = $db->prepare("UPDATE rates SET rate_value = ? WHERE rate_id = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("di", $rate_value, $rate_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

$rate = fetchRateByName($db, $rate_name);
$form_rate_value = $rate['rate_value'] ?? '0.00';

if (!$rate) {
    $message = 'The configured bill payment rate was not found.';
    $message_type = 'alert-danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_rate_value = trim($_POST['rate_value'] ?? '');
    $validation = validateRateValue($form_rate_value);

    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    } else {
        $normalized_rate_value = number_format((float) $form_rate_value, 2, '.', '');

        $db->begin_transaction();
        try {
            $locked_rate = lockRateByName($db, $rate_name);
            if (!$locked_rate) {
                throw new Exception('Rate record not found.');
            }

            if (!updateRateValue($db, (int) $locked_rate['rate_id'], (float) $normalized_rate_value)) {
                throw new Exception('Failed to update rate.');
            }

            $db->commit();
            $rate = fetchRateByName($db, $rate_name);
            $form_rate_value = $rate['rate_value'] ?? $normalized_rate_value;
            $message = 'Rate updated successfully.';
            $message_type = 'alert-success';
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while updating the rate. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Rates</li>
                        </ol>
                    </nav>

                    <?php if ($message): ?>
                        <div class="alert <?= $message_type ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white py-3">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-percentage mr-2"></i>
                                Bill Payment Rate
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($rate): ?>
                                <form method="POST" id="rateForm" novalidate>
                                    <?= csrfInputField(); ?>

                                    <div class="row">
                                        <div class="form-group col-md-6">
                                            <label for="rate_value">Rate Value (%)</label>
                                            <div class="input-group">
                                                <input
                                                    type="number"
                                                    class="form-control"
                                                    id="rate_value"
                                                    name="rate_value"
                                                    step="0.01"
                                                    min="0"
                                                    max="999.99"
                                                    required
                                                    value="<?= htmlspecialchars($form_rate_value, ENT_QUOTES, 'UTF-8'); ?>">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                            <small class="text-muted">Enter the percentage used for bill payments.</small>
                                            <div class="invalid-feedback">Please enter a valid rate value.</div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary mt-3" id="saveRateButton">
                                        <i class="fas fa-save mr-1"></i> Update Rate
                                    </button>
                                </form>
                            <?php endif; ?>
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
            $('#rateForm').on('submit', function(e) {
                var form = this;

                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    form.classList.add('was-validated');
                    return;
                }

                $('#saveRateButton')
                    .prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin mr-1"></i> Updating...');
            });
        });
    </script>
</body>
</html>
