<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/ApiController.php';

class BookingApi extends ApiController
{
    public function create_booking()
    {
        if ($this->method_not_allowed('POST, OPTIONS')) {
            return;
        }
        $user = $this->require_auth('guest');
        if (!$user) {
            return;
        }

        $data = $this->get_request_data();

        $serviceId = (int) ($data['service_id'] ?? 0);
        $checkIn = (string) ($data['check_in_date'] ?? '');
        $checkOut = (string) ($data['check_out_date'] ?? '');
        $guests = (int) ($data['guests'] ?? 0);
        $specialRequests = trim((string) ($data['special_requests'] ?? ''));

        if ($serviceId <= 0 || $checkIn === '' || $checkOut === '' || $guests <= 0) {
            $this->error('Service, dates, and guest count are required.', 422);
            return;
        }

        $service = $this->ResortService->find_active($serviceId);
        if (!$service) {
            $this->error('Service not found.', 404);
            return;
        }
        if ((int) $service['capacity'] < $guests) {
            $this->error('Guest count exceeds service capacity.', 422);
            return;
        }

        $checkInDate = DateTime::createFromFormat('!Y-m-d', $checkIn);
        $checkOutDate = DateTime::createFromFormat('!Y-m-d', $checkOut);
        if (!$checkInDate || !$checkOutDate || $checkInDate->format('Y-m-d') !== $checkIn || $checkOutDate->format('Y-m-d') !== $checkOut) {
            $this->error('Check-in and check-out must be valid dates.', 422);
            return;
        }
        if ($checkOutDate <= $checkInDate) {
            $this->error('Check-out date must be after check-in date.', 422);
            return;
        }
        $nights = (int) $checkOutDate->diff($checkInDate)->format('%a');
        $unitPrice = (float) $service['price'];
        $subtotal = $unitPrice * $nights;
        $totalAmount = $subtotal;

        $referenceNo = $this->generate_reference_no();
        $this->db->transaction();
        try {
            $unavailableDate = $this->reserve_booking_quantity($serviceId, $checkIn, $checkOut);
            if ($unavailableDate !== null) {
                $this->db->roll_back();
                $this->error(
                    'Service is unavailable for one or more selected dates.',
                    409,
                    ['unavailable_date' => $unavailableDate]
                );
                return;
            }

            $this->db->table('bookings')->insert([
                'user_id' => (int) $user['id'],
                'reference_no' => $referenceNo,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'guest_count' => $guests,
                'special_requests' => $specialRequests,
                'status' => 'Pending',
                'total_amount' => $totalAmount,
            ]);
            $bookingId = (int) $this->db->last_id();
            $this->db->table('booking_items')->insert([
                'booking_id' => $bookingId,
                'service_id' => $serviceId,
                'quantity' => $nights,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->roll_back();
            throw $exception;
        }

        $booking = $this->Booking->find_with_item($bookingId);
        $this->queue_booking_email($booking, 'submitted');

        $this->respond([
            'success' => true,
            'message' => 'Booking submitted and saved as Pending.',
            'booking' => $booking,
        ], 201);
    }

    public function my_bookings()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $user = $this->require_auth('guest');
        if (!$user) {
            return;
        }

        $rows = $this->Booking->list_for_guest((int) $user['id']);
        $this->respond(['bookings' => $rows]);
    }

    public function cancel_my_booking($bookingId)
    {
        if ($this->method_not_allowed('DELETE, OPTIONS')) {
            return;
        }
        $user = $this->require_auth('guest');
        if (!$user) {
            return;
        }

        $this->db->transaction();
        try {
            $booking = $this->Booking->find_for_guest_for_update((int) $bookingId, (int) $user['id']);
            if (!$booking) {
                $this->db->roll_back();
                $this->error('Booking not found.', 404);
                return;
            }
            if ($booking['status'] !== 'Pending') {
                $this->db->roll_back();
                $this->error('Only Pending bookings can be cancelled by guests.', 422);
                return;
            }

            $this->apply_booking_quantity(
                (int) $booking['service_id'],
                $booking['check_in_date'],
                $booking['check_out_date'],
                -1
            );
            $this->db->table('bookings')->where('booking_id', (int) $bookingId)->update([
                'status' => 'Cancelled',
            ]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->roll_back();
            throw $exception;
        }

        $updated = $booking;
        $updated['status'] = 'Cancelled';
        $this->queue_booking_email($updated, 'cancelled');
        $this->respond(['success' => true, 'message' => 'Booking cancelled.']);
    }

    public function submit_feedback($bookingId)
    {
        if ($this->method_not_allowed('POST, OPTIONS')) {
            return;
        }
        $user = $this->require_auth('guest');
        if (!$user) {
            return;
        }

        $data = $this->get_request_data();

        $booking = $this->Booking->find_completed_for_guest((int) $bookingId, (int) $user['id']);
        if (!$booking || $booking['status'] !== 'Completed') {
            $this->error('Feedback is allowed only after a completed visit.', 422);
            return;
        }

        $rating = (int) ($data['rating'] ?? 0);
        $comment = trim((string) ($data['comment'] ?? ''));
        if ($rating < 1 || $rating > 5 || $comment === '') {
            $this->error('Rating (1-5) and comment are required.', 422);
            return;
        }

        $existing = $this->db->table('feedback')->where('booking_id', (int) $bookingId)->limit(1)->get();
        if ($existing) {
            $this->error('Feedback already submitted for this booking.', 409);
            return;
        }

        $this->db->table('feedback')->insert([
            'booking_id' => (int) $bookingId,
            'user_id' => (int) $user['id'],
            'service_id' => (int) $booking['service_id'],
            'rating' => $rating,
            'comment' => $comment,
        ]);

        $this->respond(['success' => true, 'message' => 'Feedback submitted.']);
    }
}
