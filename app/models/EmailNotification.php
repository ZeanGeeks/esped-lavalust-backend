<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class EmailNotification extends Model
{
    protected $table = 'email_notifications';
    protected $primary_key = 'notification_id';

    public function create_notification($data)
    {
        $this->db->table($this->table)->insert(array_merge($data, [
            'send_status' => 'Pending',
        ]));

        return (int) $this->db->last_id();
    }

    public function mark_sent($notificationId)
    {
        $this->db->table($this->table)
            ->where($this->primary_key, $notificationId)
            ->update([
                'send_status' => 'Sent',
                'sent_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function mark_failed($notificationId, $error)
    {
        $this->db->table($this->table)
            ->where($this->primary_key, $notificationId)
            ->update([
                'send_status' => 'Failed',
                'error_message' => $error,
            ]);
    }
}
