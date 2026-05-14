<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$admin_info = getAnandaSuperAdminInfo();
if ($admin_info['admin_role'] !== 'Super Admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = '';

function validateAdminData($username, $email, $first_name, $last_name, $password): array
{
    $errors = [];

    if (empty($username) || empty($email) || empty($first_name) || empty($last_name) || empty($password)) {
        $errors[] = 'Please fill in all required fields!';
    }
    if (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters long!';
    }
    if (strlen($username) > 255) {
        $errors[] = 'Username cannot exceed 255 characters!';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address!';
    }
    if (strlen($email) > 255) {
        $errors[] = 'Email cannot exceed 255 characters!';
    }
    if (strlen($first_name) < 2) {
        $errors[] = 'First name must be at least 2 characters long!';
    }
    if (strlen($first_name) > 255) {
        $errors[] = 'First name cannot exceed 255 characters!';
    }
    if (strlen($last_name) < 2) {
        $errors[] = 'Last name must be at least 2 characters long!';
    }
    if (strlen($last_name) > 255) {
        $errors[] = 'Last name cannot exceed 255 characters!';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long!';
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number!';
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function checkAdminExists($db, $username, $email): array
{
    $stmt = $db->prepare("SELECT admin_id FROM ananda_super_admin WHERE admin_username = ? OR admin_email = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("ss", $username, $email);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'errors' => ['An admin with this username or email already exists!']];
    }
    $stmt->close();

    return ['success' => true, 'errors' => []];
}

function saveAdmin($db, $username, $email, $first_name, $last_name, $password): bool
{
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $admin_role = 'Normal Admin';
    $admin_is_active = 1;

    $stmt = $db->prepare("INSERT INTO ananda_super_admin (admin_username, admin_email, admin_first_name, admin_last_name, admin_password, admin_role, admin_is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ssssssi", $username, $email, $first_name, $last_name, $hashed_password, $admin_role, $admin_is_active);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $validation = validateAdminData($username, $email, $first_name, $last_name, $password);
    if (!$validation['success']) {
        echo json_encode(['status' => 'error', 'message' => implode('<br>', $validation['errors'])]);
        exit;
    }

    $exists = checkAdminExists($db, $username, $email);
    if (!$exists['success']) {
        echo json_encode(['status' => 'error', 'message' => implode('<br>', $exists['errors'])]);
        exit;
    }

    if (!saveAdmin($db, $username, $email, $first_name, $last_name, $password)) {
        echo json_encode(['status' => 'error', 'message' => 'An error occurred while adding the admin. Please try again.']);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'Admin added successfully!']);
    exit;
}

$page_name = "Add Admin";
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Add Admin</li>
                        </ol>
                    </nav>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-12">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-user-shield mr-2"></i>
                                        Add Normal Admin
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form id="adminForm" autocomplete="off">
                                <?= csrfInputField(); ?>

                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="username">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" minlength="3" maxlength="255" required>
                                        <small class="text-muted">Enter username (min 3 characters)</small>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="email">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" maxlength="255" required>
                                        <small class="text-muted">Enter email address</small>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="first_name">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" minlength="2" maxlength="255" required>
                                        <small class="text-muted">Enter first name</small>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="last_name">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" minlength="2" maxlength="255" required>
                                        <small class="text-muted">Enter last name</small>
                                    </div>

                                    <div class="form-group col-md-12">
                                        <label for="password">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="mt-2">
                                            <small id="rule-length" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> At least 8 characters</small>
                                            <small id="rule-uppercase" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> One uppercase letter</small>
                                            <small id="rule-lowercase" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> One lowercase letter</small>
                                            <small id="rule-number" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> One number</small>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" id="submitBtn" class="btn btn-primary mt-3" disabled>
                                    <i class="fas fa-plus mr-1"></i> Add Admin
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary mt-3">Cancel</a>
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
            const $password = $('#password');
            const $fields = $('#username, #email, #first_name, #last_name, #password');
            const $rules = {
                length: { el: $('#rule-length'), check: v => v.length >= 8 },
                uppercase: { el: $('#rule-uppercase'), check: v => /[A-Z]/.test(v) },
                lowercase: { el: $('#rule-lowercase'), check: v => /[a-z]/.test(v) },
                number: { el: $('#rule-number'), check: v => /[0-9]/.test(v) }
            };

            function setRule($rule, valid) {
                $rule.find('i').toggleClass('fa-check-circle text-success', valid).toggleClass('fa-circle text-secondary', !valid);
                $rule.toggleClass('text-success', valid).toggleClass('text-muted', !valid);
            }

            function validateForm() {
                const password = $password.val();
                const formValid = $('#username')[0].checkValidity() &&
                                  $('#email')[0].checkValidity() &&
                                  $('#first_name')[0].checkValidity() &&
                                  $('#last_name')[0].checkValidity() &&
                                  password.length > 0;

                const passwordValid = Object.entries($rules).map(([k, r]) => {
                    const v = r.check(password);
                    setRule(r.el, v);
                    return v;
                });

                $('#submitBtn').prop('disabled', !formValid || !passwordValid.every(x => x));
            }

            $password.on('input', validateForm);
            $('#username, #email, #first_name, #last_name').on('input', validateForm);

            $('#adminForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: r => r.status === 'success'
                        ? Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: r.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => location.href = 'dashboard.php')
                        : Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            html: r.message
                        }),
                    error: () => Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred.'
                    })
                });
            });
        });
    </script>
</body>
</html>
