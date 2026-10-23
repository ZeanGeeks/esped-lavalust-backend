<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AdminData extends Model
{
    public function dashboard_counts()
    {
        $pending = $this->raw("SELECT COUNT(*) AS total FROM bookings WHERE status = 'Pending'")->fetch(PDO::FETCH_ASSOC);
        $confirmed = $this->raw("SELECT COUNT(*) AS total FROM bookings WHERE status = 'Confirmed'")->fetch(PDO::FETCH_ASSOC);
        $guests = $this->raw("SELECT COUNT(*) AS total FROM users WHERE role = 'guest'")->fetch(PDO::FETCH_ASSOC);
        $revenue = $this->raw("SELECT COALESCE(SUM(total_amount), 0) AS total FROM bookings WHERE status = 'Completed'")->fetch(PDO::FETCH_ASSOC);

        return [
            'pending' => (int) $pending['total'],
            'confirmed' => (int) $confirmed['total'],
            'guests' => (int) $guests['total'],
            'revenue' => (float) $revenue['total'],
        ];
    }

    public function latest_pending_bookings()
    {
        return $this->raw(
            "SELECT b.booking_id AS id, b.reference_no, b.check_in_date, b.check_out_date,
                    b.guest_count AS guests, b.total_amount, u.full_name AS username,
                    s.service_name AS service_name
             FROM bookings b
             INNER JOIN users u ON u.user_id = b.user_id
             LEFT JOIN booking_items bi ON bi.booking_id = b.booking_id
             LEFT JOIN services s ON s.service_id = bi.service_id
             WHERE b.status = 'Pending'
             ORDER BY b.created_at DESC
             LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function booking_trend()
    {
        return $this->raw(
            "SELECT DATE(created_at) AS report_date, COUNT(*) AS bookings, COALESCE(SUM(total_amount), 0) AS revenue
             FROM bookings
             GROUP BY DATE(created_at)
             ORDER BY report_date ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function feedback_list()
    {
        return $this->raw(
            "SELECT f.feedback_id AS id, f.rating, f.comment, f.created_at,
                    b.reference_no, u.full_name AS username, s.service_name AS service_name
             FROM feedback f
             LEFT JOIN bookings b ON b.booking_id = f.booking_id
             INNER JOIN users u ON u.user_id = f.user_id
             LEFT JOIN services s ON s.service_id = f.service_id
             ORDER BY f.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function booking_report($from, $to)
    {
        return $this->raw(
            "SELECT DATE(created_at) AS report_date, COUNT(*) AS booking_count, COALESCE(SUM(total_amount), 0) AS revenue
             FROM bookings
             WHERE DATE(created_at) BETWEEN ? AND ?
             GROUP BY DATE(created_at)
             ORDER BY report_date ASC",
            [$from, $to]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activity_logs()
    {
        return $this->raw(
            "SELECT l.activity_id AS id, l.actor_user_id AS admin_user_id,
                    l.action, l.entity_type, l.entity_id, NULL AS details,
                    l.created_at, u.full_name AS admin_username
             FROM activity_logs l
             INNER JOIN users u ON u.user_id = l.actor_user_id
             ORDER BY l.created_at DESC
             LIMIT 200"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function system_settings()
    {
        return $this->raw(
            "SELECT setting_id AS id, setting_key, setting_value, updated_at
             FROM system_settings
             ORDER BY setting_key"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
