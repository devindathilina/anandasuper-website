<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$admin_info = getAnandaSuperAdminInfo();
if (!$admin_info || $admin_info['admin_role'] !== 'Super Admin') {
    header('Location: dashboard.php');
    exit;
}

function getAdminById($db, $admin_id): array
{
    $stmt = $db->prepare("SELECT admin_id, admin_username, admin_email, admin_first_name, admin_last_name, admin_role, admin_is_active FROM ananda_super_admin WHERE admin_id = ?");
    if (!$stmt) {
        return ['success' => false, 'data' => null];
    }

    $stmt->bind_param("i", $admin_id);
    if (!$stmt->execute()) {
        $stmt->close();
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

function validateAdminDataForUpdate($username, $email, $first_name, $last_name, $password = null): array
{
    $errors = [];

    if (empty($username) || strlen($username) < 3) {
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
    if (empty($first_name) || strlen($first_name) < 2) {
        $errors[] = 'First name must be at least 2 characters long!';
    }
    if (strlen($first_name) > 255) {
        $errors[] = 'First name cannot exceed 255 characters!';
    }
    if (empty($last_name) || strlen($last_name) < 2) {
        $errors[] = 'Last name must be at least 2 characters long!';
    }
    if (strlen($last_name) > 255) {
        $errors[] = 'Last name cannot exceed 255 characters!';
    }
    if ($password !== null && $password !== '') {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long!';
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number!';
        }
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function checkAdminExistsForUpdate($db, $username, $email, $admin_id): array
{
    $stmt = $db->prepare("SELECT admin_id FROM ananda_super_admin WHERE (admin_username = ? OR admin_email = ?) AND admin_id != ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("ssi", $username, $email, $admin_id);
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

function updateAdmin($db, $username, $email, $first_name, $last_name, $password, $admin_id): bool
{
    if ($password !== null && $password !== '') {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE ananda_super_admin SET admin_username = ?, admin_email = ?, admin_first_name = ?, admin_last_name = ?, admin_password = ? WHERE admin_id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("sssssi", $username, $email, $first_name, $last_name, $hashed_password, $admin_id);
    } else {
        $stmt = $db->prepare("UPDATE ananda_super_admin SET admin_username = ?, admin_email = ?, admin_first_name = ?, admin_last_name = ? WHERE admin_id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ssssi", $username, $email, $first_name, $last_name, $admin_id);
    }

    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$admin_id = (int)$_GET['id'];

$adminResult = getAdminById($db, $admin_id);
if (!$adminResult['success']) {
    header('Location: dashboard.php');
    exit;
}

$admin_data = $adminResult['data'];
$current_role = $admin_data['admin_role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_role !== 'Super Admin') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($password === '') {
        $password = null;
    }

    $validation = validateAdminDataForUpdate($username, $email, $first_name, $last_name, $password);
    if (!$validation['success']) {
        echo json_encode(['status' => 'error', 'message' => implode('<br>', $validation['errors'])]);
        exit;
    }

    $exists = checkAdminExistsForUpdate($db, $username, $email, $admin_id);
    if (!$exists['success']) {
        echo json_encode(['status' => 'error', 'message' => implode('<br>', $exists['errors'])]);
        exit;
    }

    if (!updateAdmin($db, $username, $email, $first_name, $last_name, $password, $admin_id)) {
        echo json_encode(['status' => 'error', 'message' => 'An error occurred while updating the admin. Please try again.']);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'Admin updated successfully!']);
    exit;
}

$page_name = "Edit Admin";
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Edit Admin</li>
                        </ol>
                    </nav>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-12">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-user-shield mr-2"></i>
                                        Edit Normal Admin
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($current_role === 'Super Admin'): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <strong>Access Denied:</strong> Super Admin accounts cannot be edited.
                                </div>
                                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                            <?php else: ?>
                                <form id="adminForm" autocomplete="off">
                                    <?= csrfInputField(); ?>

                                    <div class="row">
                                        <div class="form-group col-md-6">
                                            <label for="username">Username</label>
                                            <input type="text" class="form-control" id="username" name="username" minlength="3" maxlength="255" required value="<?= htmlspecialchars($admin_data['admin_username'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Enter username (min 3 characters)</small>
                                        </div>

                                        <div class="form-group col-md-6">
                                            <label for="email">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" maxlength="255" required value="<?= htmlspecialchars($admin_data['admin_email'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Enter email address</small>
                                        </div>

                                        <div class="form-group col-md-6">
                                            <label for="first_name">First Name</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" minlength="2" maxlength="255" required value="<?= htmlspecialchars($admin_data['admin_first_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Enter first name</small>
                                        </div>

                                        <div class="form-group col-md-6">
                                            <label for="last_name">Last Name</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" minlength="2" maxlength="255" required value="<?= htmlspecialchars($admin_data['admin_last_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Enter last name</small>
                                        </div>

                                        <div class="form-group col-md-12">
                                            <label for="password">New Password (Optional)</label>
                                            <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current password">
                                            <div class="mt-2">
                                                <small id="rule-length" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> At least 8 characters</small>
                                                <small id="rule-uppercase" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> One uppercase letter</small>
                                                <small id="rule-lowercase" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> One lowercase letter</small>
                                                <small id="rule-number" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> One number</small>
                                            </div>
                                            <small class="text-muted">Leave blank to keep current password</small>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="form-label">Role</label>
                                                <div class="form-control-plaintext">
                                                    <span class="badge badge-info"><?= htmlspecialchars($current_role, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <small class="form-text text-muted">Role is fixed and cannot be changed</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" id="submitBtn" class="btn btn-primary mt-3">
                                        <i class="fas fa-save mr-1"></i> Update Admin
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
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
            const $password = $('#password');
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
                                  $('#last_name')[0].checkValidity();

                const passwordValid = password === '' || Object.entries($rules).map(([k, r]) => {
                    const v = r.check(password);
                    setRule(r.el, v);
                    return v;
                }).every(x => x);

                $('#submitBtn').prop('disabled', !formValid || !passwordValid);
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
