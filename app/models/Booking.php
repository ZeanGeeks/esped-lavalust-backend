<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Booking extends Model
{
    protected $table = 'bookings';
    protected $primary_key = 'booking_id';

    public function find_with_item($bookingId)
    {
        return $this->db->table('bookings AS b')
            ->select('b.booking_id AS id, b.user_id, b.reference_no, b.check_in_date, b.check_out_date, b.guest_count AS guests, b.special_requests, b.status, b.total_amount, bi.service_id, bi.unit_price, bi.subtotal, b.created_at')
            ->inner_join('booking_items AS bi', 'bi.booking_id = b.booking_id')
            ->where('b.booking_id', $bookingId)
            ->limit(1)
            ->get();
    }

    public function list_for_guest($userId)
    {
        return $this->db->table('bookings AS b')
            ->select('b.booking_id AS id, b.user_id, b.reference_no, b.check_in_date, b.check_out_date, b.guest_count AS guests, b.special_requests, b.status, b.total_amount, b.created_at, bi.service_id, bi.unit_price, bi.subtotal, s.service_name AS service_name, s.service_type')
            ->left_join('booking_items AS bi', 'bi.booking_id = b.booking_id')
            ->left_join('services AS s', 's.service_id = bi.service_id')
            ->where('b.user_id', $userId)
            ->order_by('b.created_at', 'DESC')
            ->get_all();
    }

    public function find_for_guest($bookingId, $userId)
    {
        return $this->db->table('bookings AS b')
            ->select('b.booking_id AS id, b.user_id, b.reference_no, b.check_in_date, b.check_out_date, b.guest_count AS guests, b.status, b.total_amount, bi.service_id, bi.unit_price, bi.subtotal')
            ->left_join('booking_items AS bi', 'bi.booking_id = b.booking_id')
            ->where('b.booking_id', $bookingId)
            ->where('b.user_id', $userId)
            ->limit(1)
            ->get();
    }

    public function find_for_guest_for_update($bookingId, $userId)
    {
        return $this->db->raw(
            'SELECT b.booking_id AS id, b.user_id, b.reference_no, b.check_in_date, b.check_out_date,
                    b.guest_count AS guests, b.status, b.total_amount, bi.service_id, bi.unit_price, bi.subtotal
             FROM bookings AS b
             LEFT JOIN booking_items AS bi ON bi.booking_id = b.booking_id
             WHERE b.booking_id = ? AND b.user_id = ?
             FOR UPDATE',
            [(int) $bookingId, (int) $userId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function find_completed_for_guest($bookingId, $userId)
    {
        return $this->db->table('bookings AS b')
            ->select('b.booking_id AS id, b.user_id, b.status, bi.service_id')
            ->inner_join('booking_items AS bi', 'bi.booking_id = b.booking_id')
            ->where('b.booking_id', $bookingId)
            ->where('b.user_id', $userId)
            ->limit(1)
            ->get();
    }

    public function list_for_admin($search, $status)
    {
        $query = $this->db->table('bookings AS b')
            ->select('b.booking_id AS id, b.user_id, b.reference_no, b.check_in_date, b.check_out_date, b.guest_count AS guests, b.status, b.total_amount, b.special_requests, b.created_at, b.approved_by_admin_id, bi.service_id, bi.unit_price, bi.subtotal, u.full_name AS username, u.email, s.service_name AS service_name, s.service_type')
            ->inner_join('users AS u', 'u.id = b.user_id')
            ->left_join('booking_items AS bi', 'bi.booking_id = b.booking_id')
            ->left_join('services AS s', 's.service_id = bi.service_id');

        if ($status !== '') {
            $query->where('b.status', $status);
        }
        if ($search !== '') {
            $query->grouped(function ($query) use ($search) {
                $searchTerm = '%' . $search . '%';
                $query->like('b.reference_no', $searchTerm)
                    ->or_like('u.full_name', $searchTerm)
                    ->or_like('s.service_name', $searchTerm);
            });
        }

        return $query->order_by('b.created_at', 'DESC')->get_all();
    }

    public function find_for_admin_update_for_update($bookingId)
    {
        return $this->db->raw(
            'SELECT b.booking_id AS id, b.user_id, b.reference_no, b.check_in_date, b.check_out_date,
                    b.guest_count AS guests, b.status, b.total_amount, b.approved_by_admin_id,
                    bi.service_id, bi.unit_price, bi.subtotal
             FROM bookings AS b
             LEFT JOIN booking_items AS bi ON bi.booking_id = b.booking_id
             WHERE b.booking_id = ?
             FOR UPDATE',
            [(int) $bookingId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function calendar_events()
    {
        return $this->db->table('bookings AS b')
            ->select('b.booking_id AS id, b.reference_no, b.check_in_date, b.check_out_date, b.status, s.service_name AS service_name, u.full_name AS username')
            ->left_join('booking_items AS bi', 'bi.booking_id = b.booking_id')
            ->left_join('services AS s', 's.service_id = bi.service_id')
            ->inner_join('users AS u', 'u.id = b.user_id')
            ->in('b.status', ['Pending', 'Confirmed', 'Completed'])
            ->order_by('b.check_in_date', 'ASC')
            ->get_all();
    }
}
