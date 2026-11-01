<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiController extends Controller
{
    public function before_action()
    {
        $this->call->database();
        $this->call->library('api');
        $this->call->model([
            'User',
            'ResortService',
            'Booking',
            'ServiceAvailability',
            'AdminData',
            'EmailNotification',
        ]);
    }

    public function respond($data, $code = 200)
    {
        $this->api->respond($data, $code);
    }

    public function error($message, $code = 400, $extra = [])
    {
        $this->respond(array_merge(['success' => false, 'error' => $message], $extra), $code);
    }

    public function method_not_allowed($allowed = 'GET, OPTIONS')
    {
        if ($this->request->method(true) === 'OPTIONS') {
            $this->response->set_status_code(204)->send();
            return true;
        }

        return false;
    }

    public function get_request_data()
    {
        $json = $this->request->json(true);
        if (is_array($json) && $json !== []) {
            return $json;
        }

        $post = $this->request->post();
        if (is_array($post) && $post !== []) {
            return $post;
        }

        $raw = $this->request->raw_body();
        if (!$raw) {
            return [];
        }

        parse_str($raw, $parsed);
        return is_array($parsed) ? $parsed : [];
    }

    public function current_user()
    {
        if (!$this->api->get_bearer_token()) {
            return null;
        }

        $payload = $this->api->require_jwt();
        $user = $this->User->find_by_id((int) $payload['sub']);
        return $user && (int) $user['is_active'] === 1 ? $user : null;
    }

    public function require_auth($role = null)
    {
        $payload = $this->api->require_jwt();
        $user = $this->User->find_by_id((int) $payload['sub']);
        if (!$user) {
            $this->error('Authentication required.', 401);
            return null;
        }

        if ((int) $user['is_active'] !== 1) {
            $this->error('Account is suspended.', 403);
            return null;
        }

        if ($role && $user['role'] !== $role) {
            $this->error('Access denied.', 403);
            return null;
        }

        return $user;
    }

    public function issue_tokens($user)
    {
        return $this->api->issue_tokens([
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'scopes' => $user['role'] === 'admin' ? ['read', 'write', 'delete'] : ['read', 'write'],
        ]);
    }

    public function find_token_user($payload)
    {
        if (!isset($payload['sub']) || !is_numeric($payload['sub'])) {
            return null;
        }

        return $this->User->find_by_id((int) $payload['sub']);
    }

    public function revoke_refresh_token($token)
    {
        $this->api->revoke_refresh_token($token);
    }

    public function consume_refresh_token($token, $userId)
    {
        $refreshKey = (string) config_item('refresh_token_key');
        $hashedToken = hash_hmac('sha256', $token, $refreshKey);
        $statement = $this->db->raw(
            'DELETE FROM refresh_tokens WHERE token = ? AND user_id = ? AND expires_at > NOW()',
            [$hashedToken, (int) $userId]
        );

        return $statement->rowCount() === 1;
    }

    public function validate_refresh_token($token)
    {
        return $this->api->validate_jwt($token, 'refresh');
    }

    public function log_admin_action($adminUserId, $action, $entityType, $entityId, $details = null)
    {
        $this->db->table('activity_logs')->insert([
            'actor_user_id' => $adminUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => is_numeric($entityId) ? (int) $entityId : null,
        ]);
    }

    public function generate_reference_no()
    {
        return 'ESP-' . date('Ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function daterange($startDate, $endDate)
    {
        $dates = [];
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        while ($start < $end) {
            $dates[] = $start->format('Y-m-d');
            $start->modify('+1 day');
        }
        if ($dates === []) {
            $dates[] = (new DateTime($startDate))->format('Y-m-d');
        }
        return $dates;
    }

    public function reserve_booking_quantity($serviceId, $checkInDate, $checkOutDate)
    {
        foreach ($this->daterange($checkInDate, $checkOutDate) as $date) {
            $statement = $this->db->raw(
                'UPDATE service_availability
                 SET booked_quantity = booked_quantity + 1
                 WHERE service_id = ? AND available_date = ? AND booked_quantity < total_quantity',
                [(int) $serviceId, $date]
            );

            if ($statement->rowCount() !== 1) {
                return $date;
            }
        }

        return null;
    }

    public function apply_booking_quantity($serviceId, $checkInDate, $checkOutDate, $delta)
    {
        foreach ($this->daterange($checkInDate, $checkOutDate) as $date) {
            $this->db->raw(
                'UPDATE service_availability
                 SET booked_quantity = GREATEST(0, booked_quantity + ?)
                 WHERE service_id = ? AND available_date = ?',
                [(int) $delta, (int) $serviceId, $date]
            );
        }
    }

    public function queue_booking_email($booking, $type)
    {
        $user = $this->User->find_notification_recipient((int) $booking['user_id']);
        if (!$user) {
            return;
        }

        $subjectMap = [
            'submitted' => 'Booking submitted',
            'confirmed' => 'Booking confirmed',
            'rejected' => 'Booking rejected',
            'cancelled' => 'Booking cancelled',
            'completed' => 'Booking completed',
            'reminder' => 'Visit reminder',
        ];
        $subject = $subjectMap[$type] ?? 'Booking status update';
        $body = "Hello {$user['username']},\n\nBooking {$booking['reference_no']} is now {$booking['status']}.\nService ID: {$booking['service_id']}\nCheck-in: {$booking['check_in_date']}\nCheck-out: {$booking['check_out_date']}\n\nThank you,\nESPED Resort";
        $notificationId = $this->EmailNotification->create_notification([
            'booking_id' => (int) $booking['id'],
            'user_id' => (int) $booking['user_id'],
            'notification_type' => $type,
            'recipient_email' => $user['email'],
            'subject' => $subject,
            'body' => $body,
        ]);

        try {
            $this->send_notification_email($user['email'], $subject, $body);
            $this->EmailNotification->mark_sent($notificationId);
        } catch (Throwable $exception) {
            error_log('Booking notification email failed: ' . $exception->getMessage());
            $this->EmailNotification->mark_failed($notificationId, $exception->getMessage());
        }
    }

    private function send_notification_email($toEmail, $subject, $body)
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            throw new RuntimeException('PHPMailer is not installed. Run composer require phpmailer/phpmailer.');
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
