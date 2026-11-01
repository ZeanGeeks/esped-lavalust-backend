<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/ApiController.php';

class AdminApi extends ApiController
{
    public function admin_dashboard()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }

        $cards = $this->AdminData->dashboard_counts();
        $latestPending = $this->AdminData->latest_pending_bookings();
        $trend = $this->AdminData->booking_trend();

        $this->respond([
            'cards' => $cards,
            'chart' => $trend,
            'latest_pending' => $latestPending,
        ]);
    }

    public function admin_bookings()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }

        $search = trim((string) ($this->request->get('search') ?? ''));
        $status = trim((string) ($this->request->get('status') ?? ''));
        $rows = $this->Booking->list_for_admin($search, $status);
        $this->respond(['bookings' => $rows]);
    }

    private function update_booking_status_with_admin($bookingId, $newStatus, $emailType, $releaseQuantity = false)
    {
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }

        $this->db->transaction();
        try {
            $booking = $this->Booking->find_for_admin_update_for_update((int) $bookingId);
            if (!$booking) {
                $this->db->roll_back();
                $this->error('Booking not found.', 404);
                return;
            }

            if ($newStatus === 'Confirmed' && $booking['status'] !== 'Pending') {
                $this->db->roll_back();
                $this->error('Only Pending bookings can be confirmed.', 422);
                return;
            }

            if ($releaseQuantity && !in_array($booking['status'], ['Confirmed', 'Pending'], true)) {
                $this->db->roll_back();
                $this->error('Booking is not in a releasable state.', 422);
                return;
            }

            if ($releaseQuantity) {
                $this->apply_booking_quantity((int) $booking['service_id'], $booking['check_in_date'], $booking['check_out_date'], -1);
            }

            $this->db->table('bookings')->where('booking_id', (int) $bookingId)->update([
                'status' => $newStatus,
                'approved_by_admin_id' => (int) $admin['id'],
            ]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->roll_back();
            $this->error('Failed to update booking status.', 500);
            return;
        }

        $previousStatus = $booking['status'];
        $booking['status'] = $newStatus;
        $updated = $booking;
        $this->queue_booking_email($updated, $emailType);
        $this->log_admin_action((int) $admin['id'], 'booking_status_change', 'booking', $bookingId, json_encode([
            'from' => $previousStatus,
            'to' => $newStatus,
        ]));
        $this->respond(['success' => true, 'booking' => $updated]);
    }

    public function admin_confirm_booking($bookingId)
    {
        if ($this->method_not_allowed('PUT, OPTIONS')) {
            return;
        }
        $this->update_booking_status_with_admin($bookingId, 'Confirmed', 'confirmed');
    }

    public function admin_reject_booking($bookingId)
    {
        if ($this->method_not_allowed('PUT, OPTIONS')) {
            return;
        }
        $this->update_booking_status_with_admin($bookingId, 'Cancelled', 'rejected', true);
    }

    public function admin_complete_booking($bookingId)
    {
        if ($this->method_not_allowed('PUT, OPTIONS')) {
            return;
        }
        $this->update_booking_status_with_admin($bookingId, 'Completed', 'completed');
    }

    public function admin_booking_calendar()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }
        $rows = $this->Booking->calendar_events();
        $this->respond(['events' => $rows]);
    }

    public function admin_services()
    {
        if ($this->request->is_get()) {
            $admin = $this->require_auth('admin');
            if (!$admin) {
                return;
            }
            $rows = $this->ResortService->list_for_admin();
            $this->respond(['services' => $rows]);
            return;
        }
        if ($this->request->is_post()) {
            $admin = $this->require_auth('admin');
            if (!$admin) {
                return;
            }
            $data = $this->get_request_data();
            $name = trim((string) ($data['name'] ?? ''));
            $type = trim((string) ($data['service_type'] ?? ''));
            $description = trim((string) ($data['description'] ?? ''));
            $price = (float) ($data['price'] ?? 0);
            $capacity = (int) ($data['capacity'] ?? 0);
            if ($name === '' || !in_array($type, ['cottage', 'room', 'amenity', 'package'], true) || $price <= 0 || $capacity <= 0) {
                $this->error('Invalid service payload.', 422);
                return;
            }
            $this->db->table('services')->insert([
                'service_name' => $name,
                'service_type' => $type,
                'description' => $description,
                'price' => $price,
                'capacity' => $capacity,
                'is_active' => 1,
            ]);
            $id = (int) $this->db->last_id();
            $this->log_admin_action((int) $admin['id'], 'create_service', 'service', $id, json_encode($data));
            $this->respond(['success' => true, 'service_id' => $id], 201);
            return;
        }
        if ($this->method_not_allowed('GET, POST, OPTIONS')) {
            return;
        }
    }

    public function admin_update_service($serviceId)
    {
        if ($this->method_not_allowed('PUT, OPTIONS')) {
            return;
        }
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }
        $data = $this->get_request_data();

        $service = $this->db->table('services')->where('service_id', (int) $serviceId)->limit(1)->get();
        if (!$service) {
            $this->error('Service not found.', 404);
            return;
        }

        $update = [];
        foreach (['service_type', 'description'] as $key) {
            if (isset($data[$key])) {
                $update[$key] = trim((string) $data[$key]);
            }
        }
        if (isset($data['name'])) {
            $update['service_name'] = trim((string) $data['name']);
        }
        if (isset($data['price'])) {
            $update['price'] = (float) $data['price'];
        }
        if (isset($data['capacity'])) {
            $update['capacity'] = (int) $data['capacity'];
        }
        if (isset($data['is_active'])) {
            $update['is_active'] = (int) ((bool) $data['is_active']);
        }
        if ($update === []) {
            $this->error('No service updates provided.', 422);
            return;
        }
        $this->db->table('services')->where('service_id', (int) $serviceId)->update($update);
        $this->log_admin_action((int) $admin['id'], 'update_service', 'service', $serviceId, json_encode($update));
        $this->respond(['success' => true]);
    }

    public function admin_deactivate_service($serviceId)
    {
        if ($this->method_not_allowed('DELETE, OPTIONS')) {
            return;
        }
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }
        $this->db->table('services')->where('service_id', (int) $serviceId)->update([
            'is_active' => 0,
        ]);
        $this->log_admin_action((int) $admin['id'], 'deactivate_service', 'service', $serviceId, null);
        $this->respond(['success' => true]);
    }

    public function admin_service_availability($serviceId)
    {
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }
        if ($this->request->is_get()) {
            $rows = $this->ServiceAvailability->list_for_service((int) $serviceId);
            $this->respond(['availability' => $rows]);
            return;
        }

        if ($this->request->is_put()) {
            $data = $this->get_request_data();

            $startDate = (string) ($data['start_date'] ?? '');
            $endDate = (string) ($data['end_date'] ?? '');
            $total = (int) ($data['total_quantity'] ?? 0);
            $start = DateTime::createFromFormat('!Y-m-d', $startDate);
            $end = DateTime::createFromFormat('!Y-m-d', $endDate);
            if (
                !$start
                || !$end
                || $start->format('Y-m-d') !== $startDate
                || $end->format('Y-m-d') !== $endDate
                || $end < $start
                || (int) $start->diff($end)->format('%a') > 365
                || $total < 0
            ) {
                $this->error('Invalid availability payload.', 422);
                return;
            }

            $this->db->transaction();
            try {
                for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
                    $availableDate = $date->format('Y-m-d');
                    $existing = $this->db->table('service_availability')
                        ->where('service_id', (int) $serviceId)
                        ->where('available_date', $availableDate)
                        ->limit(1)
                        ->get();

                    if ($existing) {
                        if ($total < (int) $existing['booked_quantity']) {
                            $this->db->roll_back();
                            $this->error(
                                'Available quantity cannot be lower than the quantity already booked.',
                                422,
                                ['unavailable_date' => $availableDate]
                            );
                            return;
                        }

                        $this->db->table('service_availability')
                            ->where('availability_id', (int) $existing['availability_id'])
                            ->update(['total_quantity' => $total]);
                    } else {
                        $this->db->table('service_availability')->insert([
                            'service_id' => (int) $serviceId,
                            'available_date' => $availableDate,
                            'total_quantity' => $total,
                            'booked_quantity' => 0,
                        ]);
                    }
                }
                $this->db->commit();
            } catch (Throwable $exception) {
                $this->db->roll_back();
                throw $exception;
            }

            $this->log_admin_action((int) $admin['id'], 'update_service_availability', 'service', $serviceId, json_encode($data));
            $this->respond(['success' => true]);
            return;
        }

        if ($this->method_not_allowed('GET, PUT, OPTIONS')) {
            return;
        }
    }

    public function admin_guests()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }
        $search = trim((string) ($this->request->get('search') ?? ''));
        $rows = $this->User->list_guests($search);
        $this->respond(['guests' => $rows]);
    }

    public function admin_guest_status($guestId)
    {
        if ($this->method_not_allowed('PUT, OPTIONS')) {
            return;
        }
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }
        $data = $this->get_request_data();
        $isActive = (int) ((bool) ($data['is_active'] ?? true));
        $this->db->table('users')->where('id', (int) $guestId)->where('role', 'guest')->update([
            'account_status' => $isActive ? 'active' : 'inactive',
        ]);
        $this->log_admin_action((int) $admin['id'], 'update_guest_status', 'user', $guestId, json_encode(['is_active' => $isActive]));
        $this->respond(['success' => true]);
    }

    public function admin_feedback()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }
        $rows = $this->AdminData->feedback_list();
        $this->respond(['feedback' => $rows]);
    }

    public function admin_reports()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }
        $from = (string) ($this->request->get('from') ?? date('Y-m-01'));
        $to = (string) ($this->request->get('to') ?? date('Y-m-d'));
        $fromDate = DateTime::createFromFormat('!Y-m-d', $from);
        $toDate = DateTime::createFromFormat('!Y-m-d', $to);
        if (
            !$fromDate
            || !$toDate
            || $fromDate->format('Y-m-d') !== $from
            || $toDate->format('Y-m-d') !== $to
            || $toDate < $fromDate
        ) {
            $this->error('Choose a valid report date range.', 422);
            return;
        }

        $rows = $this->AdminData->booking_report($from, $to);
        $this->respond(['reports' => $rows, 'from' => $from, 'to' => $to]);
    }

    public function admin_activity_logs()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }
        $rows = $this->AdminData->activity_logs();
        $this->respond(['activity_logs' => $rows]);
    }

    public function admin_settings()
    {
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }

        if ($this->request->is_get()) {
            $settings = $this->AdminData->system_settings();
            $this->respond(['system_settings' => $settings, 'resort_info' => null]);
            return;
        }

        if ($this->request->is_put()) {
            $data = $this->get_request_data();
            if (isset($data['resort_info'])) {
                $this->error('Resort information is not stored in the imported database schema.', 422);
                return;
            }

            if (isset($data['system_settings']) && is_array($data['system_settings'])) {
                foreach ($data['system_settings'] as $key => $value) {
                    $existing = $this->db->table('system_settings')->where('setting_key', (string) $key)->limit(1)->get();
                    if ($existing) {
                        $this->db->table('system_settings')->where('setting_id', (int) $existing['setting_id'])->update([
                            'setting_value' => (string) $value,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    } else {
                        $this->db->table('system_settings')->insert([
                            'setting_key' => (string) $key,
                            'setting_value' => (string) $value,
                        ]);
                    }
                }
            }

            $this->log_admin_action((int) $admin['id'], 'update_settings', 'system', 'global', null);
            $this->respond(['success' => true]);
            return;
        }

        if ($this->method_not_allowed('GET, PUT, OPTIONS')) {
            return;
        }
    }

    public function admin_backup_export()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $admin = $this->require_auth('admin');
        if (!$admin) {
            return;
        }

        $tables = [
            'users',
            'services',
            'service_availability',
            'bookings',
            'booking_items',
            'feedback',
            'recommendation_requests',
            'recommendation_items',
            'system_settings',
            'email_notifications',
            'activity_logs',
        ];
        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table] = $this->db->table($table)->get_all();
        }

        $this->log_admin_action((int) $admin['id'], 'backup_export', 'system', 'database', null);
        $this->respond([
            'generated_at' => gmdate('c'),
            'snapshot' => $snapshot,
        ]);
    }
}
