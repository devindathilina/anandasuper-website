<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$message = '';
$message_type = '';

function getPaymentMethodById($db, $payment_method_id): array
{
    $stmt = $db->prepare("SELECT payment_method_name, is_active FROM payment_methods WHERE payment_method_id = ?");
    if (!$stmt) {
        return ['success' => false, 'data' => null];
    }

    $stmt->bind_param("i", $payment_method_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'data' => null];
    }

    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'data' => null];
    }

    $payment_method_name = null;
    $is_active = null;
    $stmt->bind_result($payment_method_name, $is_active);
    $stmt->fetch();
    $stmt->close();

    return ['success' => true, 'data' => ['payment_method_name' => $payment_method_name, 'is_active' => $is_active]];
}

function validatePaymentMethodData($payment_method_name): array
{
    $errors = [];

    if (empty($payment_method_name)) {
        $errors[] = 'Please fill in all required fields!';
    }
    if (strlen($payment_method_name) < 2) {
        $errors[] = 'Payment method name must be at least 2 characters long!';
    }
    if (strlen($payment_method_name) > 100) {
        $errors[] = 'Payment method name cannot exceed 100 characters!';
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function checkPaymentMethodExistsForUpdate($db, $payment_method_name, $payment_method_id): array
{
    $stmt = $db->prepare("SELECT payment_method_id FROM payment_methods WHERE payment_method_name = ? AND payment_method_id != ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("si", $payment_method_name, $payment_method_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'errors' => ['A payment method with this name already exists!']];
    }
    $stmt->close();

    return ['success' => true, 'errors' => []];
}

function updatePaymentMethod($db, $payment_method_name, $payment_method_id): bool
{
    $stmt = $db->prepare("UPDATE payment_methods SET payment_method_name = ? WHERE payment_method_id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("si", $payment_method_name, $payment_method_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: payment_methods.php');
    exit;
}

$payment_method_id = (int)$_GET['id'];

$paymentMethodResult = getPaymentMethodById($db, $payment_method_id);
if (!$paymentMethodResult['success']) {
    header('Location: payment_methods.php');
    exit;
}

$payment_method_data = $paymentMethodResult['data'];
$is_active = $payment_method_data['is_active'];

$form_payment_method_name = $payment_method_data['payment_method_name'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_payment_method_name = trim($_POST['payment_method_name']);

    $form_payment_method_name = $new_payment_method_name;

    $validation = validatePaymentMethodData($new_payment_method_name);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    $exists = checkPaymentMethodExistsForUpdate($db, $new_payment_method_name, $payment_method_id);
    if (!$exists['success']) {
        $message = implode('<br>', $exists['errors']);
        $message_type = 'alert-danger';
    }

    if (!$message) {
        $db->begin_transaction();
        try {
            if (!updatePaymentMethod($db, $new_payment_method_name, $payment_method_id)) {
                throw new Exception('Failed to update payment method.');
            }

            $db->commit();
            $message = 'Payment method updated successfully!';
            $message_type = 'alert-success';
            $form_payment_method_name = $new_payment_method_name;
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while updating the payment method. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Edit Payment Method";
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
                            <li class="breadcrumb-item"><a href="payment_methods.php" class="text-light">Payment Methods</a></li>
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Edit Payment Method</li>
                        </ol>
                    </nav>

                    <?php if ($message): ?>
                        <div class="alert <?= $message_type ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php if ($message_type === 'alert-success'): ?>
                            <script>
                                setTimeout(() => {
                                    window.location.href = 'payment_methods.php';
                                }, 2000);
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-12">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-credit-card mr-2"></i>
                                        Edit Payment Method
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="paymentMethodForm" novalidate>
                                <?= csrfInputField(); ?>
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="payment_method_name">Payment Method Name</label>
                                            <input type="text" id="payment_method_name" name="payment_method_name" class="form-control" minlength="2" maxlength="100" required value="<?= htmlspecialchars($form_payment_method_name, ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Enter payment method name</small>
                                            <div class="invalid-feedback">Please enter payment method name</div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Current Status</label>
                                            <div class="form-control-plaintext">
                                                <?php if ($is_active == 1): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                                <small class="form-text text-muted">Status can be changed from the payment methods list page using the toggle switch.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-save mr-1"></i> Update Payment Method
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
            $('#paymentMethodForm').on('submit', function(e) {
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
