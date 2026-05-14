<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$message = '';
$message_type = '';

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

function checkPaymentMethodExists($db, $payment_method_name): array
{
    $stmt = $db->prepare("SELECT payment_method_id FROM payment_methods WHERE payment_method_name = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("s", $payment_method_name);
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

function savePaymentMethod($db, $payment_method_name, $is_active): bool
{
    $stmt = $db->prepare("INSERT INTO payment_methods (payment_method_name, is_active) VALUES (?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("si", $payment_method_name, $is_active);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

$form_payment_method_name = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payment_method_name = trim($_POST['payment_method_name']);
    $is_active = 1;

    $form_payment_method_name = $payment_method_name;

    $validation = validatePaymentMethodData($payment_method_name);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    $exists = checkPaymentMethodExists($db, $payment_method_name);
    if (!$exists['success']) {
        $message = implode('<br>', $exists['errors']);
        $message_type = 'alert-danger';
    }

    if (!$message) {
        $db->begin_transaction();
        try {
            if (!savePaymentMethod($db, $payment_method_name, $is_active)) {
                throw new Exception('Failed to save payment method.');
            }

            $db->commit();
            $message = 'Payment method added successfully!';
            $message_type = 'alert-success';

            $form_payment_method_name = '';
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while adding the payment method. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Add Payment Method";
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Add Payment Method</li>
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
                                        Add Payment Method
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="paymentMethodForm" novalidate>
                                <?= csrfInputField(); ?>

                                <div class="row">
                                    <div class="form-group col-md-8">
                                        <label for="payment_method_name">Payment Method Name</label>
                                        <input type="text" class="form-control" id="payment_method_name" name="payment_method_name" minlength="2" maxlength="100" required value="<?= htmlspecialchars($form_payment_method_name, ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="text-muted">Enter payment method name</small>
                                        <div class="invalid-feedback">Please enter payment method name</div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus mr-1"></i> Add Payment Method
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
