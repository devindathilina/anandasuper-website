<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/../config/sms.php';

$message = '';
$message_type = '';

function getCustomerById($db, $customer_id): array
{
    $stmt = $db->prepare("SELECT customer_id, first_name, last_name, phone, password, is_active FROM customers WHERE customer_id = ?");
    if (!$stmt) {
        return ['success' => false, 'data' => null];
    }

    $stmt->bind_param("i", $customer_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'data' => null];
    }

    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'data' => null];
    }

    $customer_id = null;
    $first_name = null;
    $last_name = null;
    $phone = null;
    $password = null;
    $is_active = null;
    $stmt->bind_result($customer_id, $first_name, $last_name, $phone, $password, $is_active);
    $stmt->fetch();
    $stmt->close();

    return [
        'success' => true,
        'data' => [
            'customer_id' => $customer_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'password' => $password,
            'is_active' => $is_active
        ]
    ];
}

function validateCustomerDataForUpdate($first_name, $last_name, $phone, $password = null): array
{
    $errors = [];

    if (empty($first_name) || strlen($first_name) < 2) {
        $errors[] = 'First name must be at least 2 characters long!';
    }
    if (strlen($first_name) > 100) {
        $errors[] = 'First name cannot exceed 100 characters!';
    }
    if (empty($last_name) || strlen($last_name) < 2) {
        $errors[] = 'Last name must be at least 2 characters long!';
    }
    if (strlen($last_name) > 100) {
        $errors[] = 'Last name cannot exceed 100 characters!';
    }
    if (!preg_match('/^\d{10}$/', $phone)) {
        $errors[] = 'Please enter a valid 10-digit phone number!';
    }
    if ($password !== null && $password !== '' && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long!';
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function checkCustomerExistsForUpdate($db, $phone, $customer_id): array
{
    $stmt = $db->prepare("SELECT customer_id FROM customers WHERE phone = ? AND customer_id != ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("si", $phone, $customer_id);
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

function updateCustomer($db, $first_name, $last_name, $phone, $password, $customer_id): bool
{
    if ($password !== null && $password !== '') {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE customers SET first_name = ?, last_name = ?, phone = ?, password = ? WHERE customer_id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $hashed_password, $customer_id);
    } else {
        $stmt = $db->prepare("UPDATE customers SET first_name = ?, last_name = ?, phone = ? WHERE customer_id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("sssi", $first_name, $last_name, $phone, $customer_id);
    }

    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: customers.php');
    exit;
}

$customer_id = (int)$_GET['id'];

$customerResult = getCustomerById($db, $customer_id);
if (!$customerResult['success']) {
    header('Location: customers.php');
    exit;
}

$customer_data = $customerResult['data'];
$customer_id = $customer_data['customer_id'];
$is_active = $customer_data['is_active'];

$form_first_name = $customer_data['first_name'];
$form_last_name = $customer_data['last_name'];
$form_phone = $customer_data['phone'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_first_name = trim($_POST['first_name'] ?? '');
    $new_last_name = trim($_POST['last_name'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $new_password = trim($_POST['password'] ?? '');

    $form_first_name = $new_first_name;
    $form_last_name = $new_last_name;
    $form_phone = $new_phone;

    $validation = validateCustomerDataForUpdate($new_first_name, $new_last_name, $new_phone, $new_password);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    $exists = checkCustomerExistsForUpdate($db, $new_phone, $customer_id);
    if (!$exists['success']) {
        $message = implode('<br>', $exists['errors']);
        $message_type = 'alert-danger';
    }

    if (!$message) {
        $password_to_save = (!empty($new_password)) ? $new_password : null;

        $db->begin_transaction();
        try {
            if (!updateCustomer($db, $new_first_name, $new_last_name, $new_phone, $password_to_save, $customer_id)) {
                throw new Exception('Failed to update customer.');
            }

            if ($password_to_save !== null) {
                $international_phone = '94' . substr($new_phone, 1);
                $sms_message = "Your {$app_name} password has been updated. Your new password is: {$password_to_save}. Use your mobile number and this password to log in.";
                sendSMS($international_phone, $sms_message);
            }

            $db->commit();
            $message = 'Customer updated successfully!';
            $message_type = 'alert-success';

            $form_first_name = $new_first_name;
            $form_last_name = $new_last_name;
            $form_phone = $new_phone;
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while updating the customer. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Edit Customer";
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Edit Customer</li>
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
                                        Edit Customer
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
                                        <label for="password">New Password</label>
                                        <input type="password" class="form-control" id="password" name="password" minlength="6" placeholder="Leave blank to keep current password">
                                        <small class="text-muted">Optional. Enter new password (min 6 characters) or leave blank to keep current password</small>
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
                                                <small class="form-text text-muted">Status can be changed from the customers list page using the toggle switch</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-save mr-1"></i> Update Customer
                                </button>
                                <a href="customers.php" class="btn btn-secondary mt-3">Back to Customers</a>
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
                var password = $('#password').val();

                if (password !== '') {
                    if (password.length < 6) {
                        e.preventDefault();
                        e.stopPropagation();
                        $('#password').addClass('is-invalid');
                        return false;
                    } else {
                        $('#password').removeClass('is-invalid');
                    }
                }

                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                form.classList.add('was-validated');
            });

            $('#password').on('input', function() {
                if ($(this).val().length >= 6 || $(this).val() === '') {
                    $(this).removeClass('is-invalid');
                }
            });
        });
    </script>
</body>
</html>
