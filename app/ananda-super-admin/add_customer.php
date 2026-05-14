<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/../config/sms.php';

$message = '';
$message_type = '';

function validateCustomerData($first_name, $last_name, $phone, $password): array
{
    $errors = [];

    if (empty($first_name) || empty($last_name) || empty($phone) || empty($password)) {
        $errors[] = 'Please fill in all required fields!';
    }
    if (strlen($first_name) < 2) {
        $errors[] = 'First name must be at least 2 characters long!';
    }
    if (strlen($first_name) > 100) {
        $errors[] = 'First name cannot exceed 100 characters!';
    }
    if (strlen($last_name) < 2) {
        $errors[] = 'Last name must be at least 2 characters long!';
    }
    if (strlen($last_name) > 100) {
        $errors[] = 'Last name cannot exceed 100 characters!';
    }
    if (!preg_match('/^\d{10}$/', $phone)) {
        $errors[] = 'Please enter a valid 10-digit phone number!';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long!';
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function checkCustomerExists($db, $phone): array
{
    $stmt = $db->prepare("SELECT customer_id FROM customers WHERE phone = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("s", $phone);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'errors' => ['A customer with this phone number already exists!']];
    }
    $stmt->close();

    return ['success' => true, 'errors' => []];
}

function saveCustomer($db, $first_name, $last_name, $phone, $password, $is_active): bool
{
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO customers (first_name, last_name, phone, password, is_active) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $hashed_password, $is_active);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

$form_first_name = '';
$form_last_name = '';
$form_phone = '';
$form_password = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $is_active = 1;

    $form_first_name = $first_name;
    $form_last_name = $last_name;
    $form_phone = $phone;
    $form_password = $password;

    $validation = validateCustomerData($first_name, $last_name, $phone, $password);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    $exists = checkCustomerExists($db, $phone);
    if (!$exists['success']) {
        $message = implode('<br>', $exists['errors']);
        $message_type = 'alert-danger';
    }

    if (!$message) {
        $db->begin_transaction();
        try {
            if (!saveCustomer($db, $first_name, $last_name, $phone, $password, $is_active)) {
                throw new Exception('Failed to save customer.');
            }

            $international_phone = '94' . substr($phone, 1);

            $sms_message = "Welcome to {$app_name}! Your password is: {$password}. Use your mobile number and this password to log in.";
            sendSMS($international_phone, $sms_message);

            $db->commit();
            $message = 'Customer added successfully!';
            $message_type = 'alert-success';

            $form_first_name = '';
            $form_last_name = '';
            $form_phone = '';
            $form_password = '';
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while adding the customer. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Add Customer";
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
                            <li class="breadcrumb-item"><a href="customers.php" class="text-light">Customers</a></li>
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Add Customer</li>
                        </ol>
                    </nav>

                    <?php if ($message): ?>
                        <div class="alert <?= $message_type ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php if ($message_type === 'alert-success'): ?>
                            <script>
                                setTimeout(() => {
                                    window.location.href = 'customers.php';
                                }, 2000);
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-12">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-user mr-2"></i>
                                        Add Customer
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="customerForm" novalidate>
                                <?= csrfInputField(); ?>

                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="first_name">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" minlength="2" maxlength="100" required value="<?= htmlspecialchars($form_first_name, ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="text-muted">Enter first name</small>
                                        <div class="invalid-feedback">Please enter first name</div>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="last_name">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" minlength="2" maxlength="100" required value="<?= htmlspecialchars($form_last_name, ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="text-muted">Enter last name</small>
                                        <div class="invalid-feedback">Please enter last name</div>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" maxlength="10" pattern="\d{10}" required value="<?= htmlspecialchars($form_phone, ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="text-muted">Enter 10-digit phone number</small>
                                        <div class="invalid-feedback">Please enter a valid 10-digit phone number</div>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="password">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" minlength="6" required value="<?= htmlspecialchars($form_password, ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="text-muted">Enter password (min 6 characters)</small>
                                        <div class="invalid-feedback">Please enter a password</div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus mr-1"></i> Add Customer
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
            $('#customerForm').on('submit', function(e) {
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
