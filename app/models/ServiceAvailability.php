<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ServiceAvailability extends Model
{
    protected $table = 'service_availability';
    protected $primary_key = 'availability_id';

    public function find_for_service_date($serviceId, $date)
    {
        return $this->db->table($this->table)
            ->select('availability_id AS id, service_id, available_date, total_quantity, booked_quantity')
            ->where('service_id', $serviceId)
            ->where('available_date', $date)
            ->limit(1)
            ->get();
    }

    public function find_for_quantity_update($serviceId, $date)
    {
        return $this->db->table($this->table)
            ->select('availability_id AS id, booked_quantity')
            ->where('service_id', $serviceId)
            ->where('available_date', $date)
            ->limit(1)
            ->get();
    }

    public function list_for_service($serviceId)
    {
        return $this->db->table($this->table)
            ->select('availability_id AS id, service_id, available_date, total_quantity, booked_quantity')
            ->where('service_id', $serviceId)
            ->order_by('available_date', 'ASC')
            ->get_all();
    }
}
