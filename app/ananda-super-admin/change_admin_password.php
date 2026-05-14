<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($current_password)) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is required.']);
        exit;
    }

    if (empty($new_password)) {
        echo json_encode(['status' => 'error', 'message' => 'New password is required.']);
        exit;
    }

    if (empty($confirm_password)) {
        echo json_encode(['status' => 'error', 'message' => 'Please confirm your new password.']);
        exit;
    }

    if (strlen($new_password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'New password must be at least 8 characters.']);
        exit;
    }

    if (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        echo json_encode(['status' => 'error', 'message' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.']);
        exit;
    }

    if ($current_password === $new_password) {
        echo json_encode(['status' => 'error', 'message' => 'New password must be different from current password.']);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'New passwords do not match.']);
        exit;
    }

    $admin_info = getAnandaSuperAdminInfo();
    if (!$admin_info) {
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
        exit;
    }

    $admin_id = $admin_info['admin_id'];

    try {
        $stmt = $db->prepare("SELECT admin_password FROM ananda_super_admin WHERE admin_id = ?");
        if (!$stmt || !$stmt->bind_param('i', $admin_id) || !$stmt->execute()) {
            throw new Exception();
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current_password, $row['admin_password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']);
            exit;
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE ananda_super_admin SET admin_password = ? WHERE admin_id = ?");
        if (!$stmt || !$stmt->bind_param('si', $hashed_password, $admin_id) || !$stmt->execute()) {
            throw new Exception();
        }

        echo json_encode(['status' => 'success', 'message' => 'Password changed successfully!']);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update password. Please try again.']);
    } finally {
        if (isset($stmt)) $stmt->close();
    }
    exit;
}

$page_name = "Change Password";
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Change Password</li>
                        </ol>
                    </nav>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-key mr-2"></i>Change Password
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form id="changePasswordForm" autocomplete="off">
                                        <?= csrfInputField() ?>
                                        <div class="form-group">
                                            <label for="current_password">Current Password</label>
                                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="new_password">New Password</label>
                                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                                            <div class="mt-2">
                                                <small id="rule-length" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> At least 8 characters</small>
                                                <small id="rule-uppercase" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> One uppercase letter</small>
                                                <small id="rule-lowercase" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> One lowercase letter</small>
                                                <small id="rule-number" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> One number</small>
                                                <small id="rule-different" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> Different from current password</small>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="confirm_password">Confirm New Password</label>
                                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                            <small id="rule-match" class="form-text text-muted d-block"><i class="fas fa-circle text-secondary mr-1"></i> Passwords match</small>
                                        </div>
                                        <button type="submit" id="submitBtn" class="btn btn-primary" disabled>Change Password</button>
                                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                    </form>
                                </div>
                            </div>
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
            const $fields = $('#new_password, #confirm_password, #current_password');
            const $rules = {
                length: { el: $('#rule-length'), check: v => v.length >= 8 },
                uppercase: { el: $('#rule-uppercase'), check: v => /[A-Z]/.test(v) },
                lowercase: { el: $('#rule-lowercase'), check: v => /[a-z]/.test(v) },
                number: { el: $('#rule-number'), check: v => /[0-9]/.test(v) },
                different: { el: $('#rule-different'), check: (v, c) => v !== c && v.length > 0 },
                match: { el: $('#rule-match'), check: (v, c, m) => v === m && v.length > 0 }
            };

            function setRule($rule, valid) {
                $rule.find('i').toggleClass('fa-check-circle text-success', valid).toggleClass('fa-circle text-secondary', !valid);
                $rule.toggleClass('text-success', valid).toggleClass('text-muted', !valid);
            }

            function validate() {
                const p = $('#new_password').val(), c = $('#current_password').val(), m = $('#confirm_password').val();
                const valid = Object.entries($rules).map(([k, r]) => {
                    const v = r.check(p, c, m);
                    setRule(r.el, v);
                    return v;
                });
                $('#submitBtn').prop('disabled', !valid.every(x => x) || !c.length);
            }

            $fields.on('input', validate);

            $('#changePasswordForm').on('submit', function(e) {
                e.preventDefault();
                $.post('', $(this).serialize(), null, 'json')
                    .done(res => {
                        if (res.status === 'success') {
                            Swal.fire({ icon: 'success', title: 'Success', text: res.message, timer: 2000, showConfirmButton: false })
                                .then(() => location.href = 'dashboard.php');
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                        }
                    })
                    .fail(() => {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'An unexpected error occurred.' });
                    });
            });
        });
    </script>
</body>

</html>