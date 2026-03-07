<?php
// includes/mail.php
// Email helper using PHPMailer + SMTP (Gmail or your provider)

require_once __DIR__ . '/../config/app.php';

// Load Composer autoloader for PHPMailer (run in project root: composer require phpmailer/phpmailer)
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Before I Grow Up');
}
if (!defined('APP_FROM_EMAIL')) {
    // Your Gmail or domain email address (sender)
    define('APP_FROM_EMAIL', 'beforeigrowup1@gmail.com');
}

// SMTP configuration — change these to your real SMTP details
if (!defined('APP_SMTP_HOST')) {
    define('APP_SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('APP_SMTP_USER')) {
    // Usually same as APP_FROM_EMAIL
    define('APP_SMTP_USER', APP_FROM_EMAIL);
}
if (!defined('APP_SMTP_PASS')) {
    // Put your Gmail app-specific password here (not your normal password)
    define('APP_SMTP_PASS', 'mbtsmpvjvclyjhiw');
}
if (!defined('APP_SMTP_PORT')) {
    define('APP_SMTP_PORT', 587); // 587 for TLS, 465 for SSL
}
if (!defined('APP_SMTP_SECURE')) {
    // Use 'tls' or 'ssl' as string; avoids fatal error if PHPMailer is missing
    define('APP_SMTP_SECURE', 'tls');
}

/**
 * Build an absolute URL for use in emails.
 */
function appUrl(string $path): string {
    $base   = defined('BASE_PATH') ? BASE_PATH : '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $base . '/' . ltrim($path, '/');
}

/**
 * Send an HTML email using PHPMailer + SMTP.
 */
function sendEmail(string $to, string $subject, string $htmlBody): bool {
    if (!class_exists(PHPMailer::class)) {
        // PHPMailer not installed / autoloaded
        error_log('PHPMailer not available. Install with composer require phpmailer/phpmailer');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = APP_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = APP_SMTP_USER;
        $mail->Password   = APP_SMTP_PASS;
        $mail->SMTPSecure = APP_SMTP_SECURE;
        $mail->Port       = APP_SMTP_PORT;

        // Recipients
        $mail->setFrom(APP_FROM_EMAIL, APP_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $e->getMessage());
        return false;
    }
}