<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ResortService extends Model
{
    protected $table = 'services';
    protected $primary_key = 'service_id';

    public function featured()
    {
        return $this->db->table($this->table)
            ->select('service_id AS id, service_name AS name, service_type, description, price, capacity')
            ->where('is_active', 1)
            ->order_by('price', 'ASC')
            ->limit(6)
            ->get_all();
    }

    public function search_active($search, $serviceType, $sort)
    {
        $query = $this->db->table($this->table)
            ->select('service_id AS id, service_name AS name, service_type, description, price, capacity, is_active')
            ->where('is_active', 1);

        if ($serviceType !== '') {
            $query->where('service_type', $serviceType);
        }
        if ($search !== '') {
            $query->grouped(function ($query) use ($search) {
                $searchTerm = '%' . $search . '%';
                $query->like('service_name', $searchTerm)
                    ->or_like('description', $searchTerm);
            });
        }

        $sort = strtoupper($sort) === 'DESC' ? 'DESC' : 'ASC';

        return $query->order_by('price', $sort)->get_all();
    }

    public function recommend_within_budget_and_capacity($maximumBudget, $guests)
    {
        return $this->db->table($this->table)
            ->select('service_id AS id, service_name AS name, service_type, description, price, capacity')
            ->where('is_active', 1)
            ->where('price', '<=', $maximumBudget)
            ->where('capacity', '>=', $guests)
            ->order_by('price', 'ASC')
            ->get_all();
    }

    public function find_active($serviceId)
    {
        return $this->db->table($this->table)
            ->select('service_id AS id, service_name AS name, service_type, description, price, capacity')
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->limit(1)
            ->get();
    }

    public function list_for_admin()
    {
        return $this->db->table($this->table)
            ->select('service_id AS id, service_name AS name, service_type, description, price, capacity, image_url, is_active')
            ->order_by('service_id', 'DESC')
            ->get_all();
    }
}
