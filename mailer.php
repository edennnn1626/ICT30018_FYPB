<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once ('./vendor/autoload.php');

function send_email($to, $subject, $body, $from_email = 'fyptesting@silvergleam.stream', $from_name = 'Team Deadline Dominator') {
    $mail = new PHPMailer(true);
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'mail.smtp2go.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'fyptesting';
        $mail->Password   = 'password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS encryption
        $mail->Port       = 2525;

        // Sender & recipient
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);

        // Email content
        $mail->isHTML(false); // plain text
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return $mail->ErrorInfo;
    }
}
?>