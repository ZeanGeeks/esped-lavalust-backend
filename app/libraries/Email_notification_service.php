<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Email_notification_service
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function queue_and_send($payload)
    {
        $insert = [
            'booking_id' => $payload['booking_id'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
            'notification_type' => $payload['email_type'],
            'send_status' => 'Pending',
        ];

        $this->_lava->db->table('email_notifications')->insert($insert);
        $notificationId = (int) $this->_lava->db->last_id();

        try {
            $this->send_email($payload['recipient_email'], $payload['subject'], $payload['body']);
            $this->_lava->db
                ->table('email_notifications')
                ->where('notification_id', $notificationId)
                ->update([
                    'send_status' => 'Sent',
                    'sent_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (Throwable $exception) {
            error_log('Booking notification email failed: ' . $exception->getMessage());
            $this->_lava->db
                ->table('email_notifications')
                ->where('notification_id', $notificationId)
                ->update([
                    'send_status' => 'Failed',
                ]);
        }

        return $notificationId;
    }

    private function send_email($toEmail, $subject, $body)
    {
        $vendorAutoload = ROOT_DIR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!file_exists($vendorAutoload)) {
            throw new RuntimeException('PHPMailer is not installed. Run composer require phpmailer/phpmailer.');
        }

        require_once $vendorAutoload;

        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            throw new RuntimeException('PHPMailer classes are not available.');
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: '';
        $mail->Port = (int) (getenv('SMTP_PORT') ?: 587);
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USERNAME') ?: '';
        $mail->Password = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = getenv('SMTP_ENCRYPTION') ?: 'tls';
        $mail->setFrom(getenv('SMTP_FROM_EMAIL') ?: 'no-reply@espedresort.com', getenv('SMTP_FROM_NAME') ?: 'ESPED Resort');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nl2br($body);
        $mail->AltBody = $body;
        $mail->send();
    }
}
