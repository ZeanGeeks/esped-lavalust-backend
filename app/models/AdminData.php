<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AdminData extends Model
{
    public function dashboard_counts()
    {
        $pending = $this->db->table('bookings')
            ->select_count('booking_id', 'total')
            ->where('status', 'Pending')
            ->get();
        $confirmed = $this->db->table('bookings')
            ->select_count('booking_id', 'total')
            ->where('status', 'Confirmed')
            ->get();
        $guests = $this->db->table('users')
            ->select_count('id', 'total')
            ->where('role', 'guest')
            ->get();
        $revenue = $this->db->table('bookings')
            ->select_sum('total_amount', 'total')
            ->where('status', 'Completed')
            ->get();

        return [
            'pending' => (int) ($pending['total'] ?? 0),
            'confirmed' => (int) ($confirmed['total'] ?? 0),
            'guests' => (int) ($guests['total'] ?? 0),
            'revenue' => (float) ($revenue['total'] ?? 0),
        ];
    }

    public function latest_pending_bookings()
    {
        return $this->db->table('bookings AS b')
            ->select('b.booking_id AS id, b.reference_no, b.check_in_date, b.check_out_date, b.guest_count AS guests, b.total_amount, u.full_name AS username, s.service_name AS service_name')
            ->inner_join('users AS u', 'u.id = b.user_id')
            ->left_join('booking_items AS bi', 'bi.booking_id = b.booking_id')
            ->left_join('services AS s', 's.service_id = bi.service_id')
            ->where('b.status', 'Pending')
            ->order_by('b.created_at', 'DESC')
            ->limit(10)
            ->get_all();
    }

    public function booking_trend()
    {
        return $this->db->table('bookings')
            ->select('DATE(created_at) AS report_date')
            ->select_count('booking_id', 'bookings')
            ->select_sum('total_amount', 'revenue')
            ->group_by('DATE(created_at)')
            ->order_by('report_date', 'ASC')
            ->get_all();
    }

    public function feedback_list()
    {
        return $this->db->table('feedback AS f')
            ->select('f.feedback_id AS id, f.rating, f.comment, f.created_at, b.reference_no, u.full_name AS username, s.service_name AS service_name')
            ->left_join('bookings AS b', 'b.booking_id = f.booking_id')
            ->inner_join('users AS u', 'u.id = f.user_id')
            ->left_join('services AS s', 's.service_id = f.service_id')
            ->order_by('f.created_at', 'DESC')
            ->get_all();
    }

    public function booking_report($from, $to)
    {
        return $this->db->table('bookings')
            ->select('DATE(created_at) AS report_date')
            ->select_count('booking_id', 'booking_count')
            ->select_sum('total_amount', 'revenue')
            ->between('DATE(created_at)', $from, $to)
            ->group_by('DATE(created_at)')
            ->order_by('report_date', 'ASC')
            ->get_all();
    }

    public function activity_logs()
    {
        $logs = $this->db->table('activity_logs AS l')
            ->select('l.activity_id AS id, l.actor_user_id AS admin_user_id, l.action, l.entity_type, l.entity_id, l.created_at, u.full_name AS admin_username')
            ->inner_join('users AS u', 'u.id = l.actor_user_id')
            ->order_by('l.created_at', 'DESC')
            ->limit(200)
            ->get_all();

        foreach ($logs as &$log) {
            $log['details'] = null;
        }
        unset($log);

        return $logs;
    }

    public function system_settings()
    {
        return $this->db->table('system_settings')
            ->select('setting_id AS id, setting_key, setting_value, updated_at')
            ->order_by('setting_key', 'ASC')
            ->get_all();
    }
}
