<?php
// Shared mailer helper using PHPMailer

function load_mailer_classes(): void
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    $phpmailerPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    $phpmailerExceptionPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    $phpmailerSmtpPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

    if (file_exists($autoload)) {
        require_once $autoload;
        return;
    }

    if (file_exists($phpmailerPath) && file_exists($phpmailerExceptionPath) && file_exists($phpmailerSmtpPath)) {
        require_once $phpmailerExceptionPath;
        require_once $phpmailerSmtpPath;
        require_once $phpmailerPath;
        return;
    }
}

function send_app_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): array
{
    $configPath = __DIR__ . '/../config/mail.php';
    if (!file_exists($configPath)) {
        return ['success' => false, 'error' => 'Mail config not found'];
    }

    $config = require $configPath;
    load_mailer_classes();

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return ['success' => false, 'error' => 'PHPMailer is not installed'];
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->Port = $config['port'];
        $mail->SMTPSecure = $config['encryption'];

        if (!empty($config['debug'])) {
            $mail->SMTPDebug = 2;
        }

        $fromEmail = $config['from_email'] ?: $config['username'];
        $mail->setFrom($fromEmail, $config['from_name']);

        if (!empty($config['reply_to'])) {
            $mail->addReplyTo($config['reply_to'], $config['from_name']);
        }

        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);

        // Embed Company Logo
        $logoPath = __DIR__ . '/../static/images/logo.JPG';
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'company_logo', 'logo.JPG', 'base64', 'image/jpeg');
        }

        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody !== '' ? $altBody : strip_tags($htmlBody);

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    }
}
