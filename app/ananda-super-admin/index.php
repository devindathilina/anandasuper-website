<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once 'auth/secure_session.php';
require_once 'auth/security_headers.php';
require_once '../config/db.php';

if (!startSecureSession()) {
    http_response_code(500);
    die('Server Error');
}

setSecurityHeaders('admin');

if (empty($_SESSION['ananda_super_admin_login_csrf_token'])) {
    $_SESSION['ananda_super_admin_login_csrf_token'] = bin2hex(random_bytes(32));
}

$page_name = "Admin Login";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($app_name); ?> | <?= htmlspecialchars($page_name); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="img/logo.png" type="image/x-icon" />
    <link href="https://fonts.googleapis.com/css?family=Nunito:300,400,700" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center w-100 mx-0">
            <div class="col-xl-6 col-lg-7 col-md-9 col-12 px-2 px-sm-3">
                <div class="card o-hidden border-0 shadow-lg">
                    <div class="card-body p-0">
                        <div class="text-center mt-4 mt-sm-5">
                            <img src="img/logo.png" alt="ANANDA SUPER Logo" class="img-fluid" style="max-width: 100px;">
                        </div>
                        <div class="p-4 p-sm-5">
                            <div class="text-center mb-5">
                                <h1 class="h3 text-gray-900">Admin Login</h1>
                                <h1 class="h5 text-gray-900"><?= htmlspecialchars($app_name); ?></h1>
                            </div>

                            <form id="admin-login-form" method="POST" style="display: block;">
                                <input type="hidden" name="ananda_super_admin_login_csrf_token" value="<?= htmlspecialchars($_SESSION['ananda_super_admin_login_csrf_token']); ?>">
                                <div class="form-group mb-4">
                                    <input type="text" class="form-control form-control-lg" name="username" id="username" placeholder="Username" required style="height: 60px; font-size: 1.2rem;">
                                </div>
                                <div class="form-group mb-4">
                                    <input type="password" class="form-control form-control-lg" name="password" id="password" placeholder="Password" required style="height: 60px; font-size: 1.2rem;">
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg btn-block" id="login-btn" style="height: 55px; font-size: 1.1rem;">Login</button>
                            </form>

                            <form id="admin-otp-form" method="POST" style="display: none;">
                                <input type="hidden" name="ananda_super_admin_otp_csrf_token" value="<?= htmlspecialchars($_SESSION['ananda_super_admin_login_csrf_token']); ?>">
                                <input type="hidden" name="admin_id" id="admin_id" value="">
                                <div class="text-center mb-4">
                                    <h1 class="h4 text-gray-900">Enter Verification Code</h1>
                                    <p class="text-muted">We've sent a verification code to your email</p>
                                </div>
                                <div class="form-group mb-4">
                                    <input type="text" class="form-control form-control-lg text-center" name="otp_code" id="otp_code" placeholder="Enter 6-digit code" maxlength="6" required style="letter-spacing: 4px; font-size: 1.5rem; height: 65px;">
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg btn-block mb-3" id="verify-btn" style="height: 50px; font-size: 1.05rem;">Verify Code</button>
                                <button type="button" class="btn btn-outline-secondary btn-lg btn-block mb-3" id="resend-btn" style="height: 50px; font-size: 1.05rem;">Resend Code</button>
                                <button type="button" class="btn btn-link btn-block" id="back-to-login" style="font-size: 1rem;">← Back to Login</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.js"></script>

    <script>
        $(document).ready(function() {
            const $elements = {
                loginForm: $('#admin-login-form'),
                otpForm: $('#admin-otp-form'),
                backToLoginBtn: $('#back-to-login'),
                resendBtn: $('#resend-btn'),
                username: $('#username'),
                password: $('#password'),
                otpCode: $('#otp_code'),
                loginBtn: $('#login-btn'),
                verifyBtn: $('#verify-btn'),
                adminId: $('#admin_id')
            };

            const utils = {
                showLoading: (title, text) => {
                    return Swal.fire({
                        title,
                        text,
                        icon: 'info',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading()
                    });
                },

                showError: (title, text) => {
                    return Swal.fire({
                        icon: 'error',
                        title,
                        text,
                        confirmButtonColor: '#dc3545'
                    });
                },

                showSuccess: (title, text) => {
                    return Swal.fire({
                        icon: 'success',
                        title,
                        text,
                        confirmButtonColor: '#28a745'
                    });
                },

                setButtonState: ($button, disabled, text) => {
                    $button.prop('disabled', disabled).text(text);
                },

                makeRequest: async (url, formData, $button, buttonText) => {
                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            body: formData
                        });
                        return await response.json();
                    } catch (error) {
                        utils.showError('Connection Error', 'Unable to connect to server. Please try again.');
                        utils.setButtonState($button, false, buttonText);
                        throw error;
                    }
                },

                startCountdown: ($button, seconds = 60) => {
                    let countdown = seconds;
                    const interval = setInterval(() => {
                        $button.text(`Resend Code (${countdown}s)`);
                        countdown--;

                        if (countdown < 0) {
                            clearInterval(interval);
                            utils.setButtonState($button, false, 'Resend Code');
                        }
                    }, 1000);
                }
            };

            const showOtpForm = () => {
                $elements.loginForm.hide();
                $elements.otpForm.show();
                $elements.otpCode.focus();
            };

            const showLoginForm = () => {
                $elements.otpForm.hide();
                $elements.loginForm.show();
                $elements.username.focus();
                $elements.otpCode.val('');
            };

            const handleLogin = async (e) => {
                e.preventDefault();

                const username = $elements.username.val().trim();
                const password = $elements.password.val().trim();

                if (!username || !password) {
                    utils.showError('Missing Information', 'Please enter both username and password');
                    return;
                }

                utils.setButtonState($elements.loginBtn, true, 'Logging in...');
                utils.showLoading('Logging in...', 'Please wait while we verify your credentials');

                try {
                    const data = await utils.makeRequest('auth/admin-login.php', new FormData($elements.loginForm[0]), $elements.loginBtn, 'Login');

                    if (data.success) {
                        if (data.requires_otp) {
                            $elements.adminId.val(data.admin_id);
                            await utils.showSuccess('Verification Required', 'We\'ve sent a verification code to your email. Please check your inbox.');
                            showOtpForm();
                        } else {
                            await utils.showSuccess('Login Successful', 'Welcome back!');
                            window.location.href = 'dashboard.php';
                        }
                    } else {
                        utils.showError('Login Failed', data.message);
                    }
                } catch (error) {
                }
                utils.setButtonState($elements.loginBtn, false, 'Login');
            };

            const handleOtpVerification = async (e) => {
                e.preventDefault();

                const otpCode = $elements.otpCode.val().trim();

                if (!otpCode || otpCode.length !== 6) {
                    utils.showError('Invalid Code', 'Please enter a valid 6-digit verification code');
                    return;
                }

                utils.setButtonState($elements.verifyBtn, true, 'Verifying...');
                utils.showLoading('Verifying Code...', 'Please wait while we verify your code');

                try {
                    const data = await utils.makeRequest('auth/admin-otp-verify.php', new FormData($elements.otpForm[0]), $elements.verifyBtn, 'Verify Code');

                    if (data.success) {
                        await utils.showSuccess('Verification Successful', 'Welcome to admin dashboard!');
                        window.location.href = 'dashboard.php';
                    } else {
                        utils.showError('Verification Failed', data.message);
                    }
                } catch (error) {
                }
                utils.setButtonState($elements.verifyBtn, false, 'Verify Code');
            };

            const handleResendOtp = async () => {
                const adminId = $elements.adminId.val();

                if (!adminId) {
                    await utils.showError('Error', 'Session expired. Please login again.');
                    location.reload();
                    return;
                }

                utils.setButtonState($elements.resendBtn, true, 'Sending...');

                try {
                    const formData = new FormData();
                    formData.append('admin_id', adminId);
                    formData.append('ananda_super_admin_otp_csrf_token', $('input[name="ananda_super_admin_otp_csrf_token"]').val());

                    const data = await utils.makeRequest('auth/admin-otp-resend.php', formData, $elements.resendBtn, 'Resend Code');

                    if (data.success) {
                        await utils.showSuccess('Code Resent', 'A new verification code has been sent to your email');
                        utils.startCountdown($elements.resendBtn);
                    } else {
                        utils.showError('Resend Failed', data.message);
                    }
                } catch (error) {
                }
            };

            $elements.loginForm.on('submit', handleLogin);
            $elements.otpForm.on('submit', handleOtpVerification);
            $elements.backToLoginBtn.on('click', showLoginForm);
            $elements.resendBtn.on('click', handleResendOtp);

            $elements.otpCode.on('input', function() {
                $(this).val($(this).val().replace(/[^0-9]/g, '').substring(0, 6));
            });
        });
    </script>
</body>

</html>