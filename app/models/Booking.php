<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Booking extends Model
{
    protected $table = 'bookings';
    protected $primary_key = 'booking_id';

    public function find_with_item($bookingId)
    {
        return $this->raw(
            "SELECT b.booking_id AS id, b.user_id, b.reference_no, b.check_in_date, b.check_out_date,
                    b.guest_count AS guests, b.special_requests, b.status, b.total_amount,
                    bi.service_id, bi.unit_price, bi.subtotal, b.created_at
             FROM bookings b
             INNER JOIN booking_items bi ON bi.booking_id = b.booking_id
             WHERE b.booking_id = ?
             LIMIT 1",
            [$bookingId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function list_for_guest($userId)
    {
        return $this->raw(
            "SELECT b.booking_id AS id, b.user_id, b.reference_no, b.check_in_date, b.check_out_date,
                    b.guest_count AS guests, b.special_requests, b.status, b.total_amount, b.created_at,
                    bi.service_id, bi.unit_price, bi.subtotal,
                    s.service_name AS service_name, s.service_type
             FROM bookings b
             LEFT JOIN booking_items bi ON bi.booking_id = b.booking_id
             LEFT JOIN services s ON s.service_id = bi.service_id
             WHERE b.user_id = ?
             ORDER BY b.created_at DESC",
            [$userId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find_for_guest($bookingId, $userId)
    {
        return $this->raw(
            "SELECT b.booking_id AS id, b.user_id, b.reference_no, b.check_in_date, b.check_out_date,
                    b.guest_count AS guests, b.status, b.total_amount,
                    bi.service_id, bi.unit_price, bi.subtotal
             FROM bookings b
             LEFT JOIN booking_items bi ON bi.booking_id = b.booking_id
             WHERE b.booking_id = ? AND b.user_id = ?
             LIMIT 1",
            [$bookingId, $userId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function find_completed_for_guest($bookingId, $userId)
    {
        return $this->raw(
            "SELECT b.booking_id AS id, b.user_id, b.status, bi.service_id
             FROM bookings b
             INNER JOIN booking_items bi ON bi.booking_id = b.booking_id
             WHERE b.booking_id = ? AND b.user_id = ?
             LIMIT 1",
            [$bookingId, $userId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function list_for_admin($search, $status)
    {
        $sql = "SELECT b.booking_id AS id, b.user_id, b.reference_no, b.check_in_date, b.check_out_date,
                       b.guest_count AS guests, b.status, b.total_amount, b.special_requests, b.created_at,
                       b.approved_by_admin_id, bi.service_id, bi.unit_price, bi.subtotal,
                       u.full_name AS username, u.email, s.service_name AS service_name, s.service_type
                FROM bookings b
                INNER JOIN users u ON u.user_id = b.user_id
                LEFT JOIN booking_items bi ON bi.booking_id = b.booking_id
                LEFT JOIN services s ON s.service_id = bi.service_id
                WHERE 1=1";
        $params = [];
        if ($status !== '') {
            $sql .= ' AND b.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $sql .= ' AND (b.reference_no LIKE ? OR u.full_name LIKE ? OR s.service_name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY b.created_at DESC';

        return $this->raw($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find_for_admin_update($bookingId)
    {
        return $this->raw(
            "SELECT b.booking_id AS id, b.user_id, b.reference_no, b.check_in_date, b.check_out_date,
                    b.guest_count AS guests, b.status, b.total_amount, b.approved_by_admin_id,
                    bi.service_id, bi.unit_price, bi.subtotal
             FROM bookings b
             LEFT JOIN booking_items bi ON bi.booking_id = b.booking_id
             WHERE b.booking_id = ?
             LIMIT 1",
            [$bookingId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function calendar_events()
    {
        return $this->raw(
            "SELECT b.booking_id AS id, b.reference_no, b.check_in_date, b.check_out_date,
                    b.status, s.service_name AS service_name, u.full_name AS username
             FROM bookings b
             LEFT JOIN booking_items bi ON bi.booking_id = b.booking_id
             LEFT JOIN services s ON s.service_id = bi.service_id
             INNER JOIN users u ON u.user_id = b.user_id
             WHERE b.status IN ('Confirmed','Completed')
             ORDER BY b.check_in_date ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
