<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using PHPMailer.
 *
 * @param string $subject The email subject
 * @param string $body The email body (HTML content)
 * @param string $email The recipient's email address
 * @param string $name The recipient's name (optional)
 * @return bool True if email sent, false otherwise
 */
function sendEmail($subject, $body, $email, $name = '')
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USERNAME'];
        $mail->Password   = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'];

        $from_email = $_ENV['FROM_EMAIL'];
        $from_name  = $_ENV['FROM_NAME'];
        $mail->setFrom($from_email, $from_name);

        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send error: " . $e->getMessage());
        return false;
    }
}
?>