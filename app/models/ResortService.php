<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ResortService extends Model
{
    protected $table = 'services';
    protected $primary_key = 'service_id';

    public function featured()
    {
        return $this->raw(
            "SELECT service_id AS id, service_name AS name, service_type, description, price, capacity
             FROM services
             WHERE is_active = 1
             ORDER BY price ASC
             LIMIT 6"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search_active($search, $serviceType, $sort)
    {
        $sql = 'SELECT service_id AS id, service_name AS name, service_type, description, price, capacity, is_active FROM services WHERE is_active = 1';
        $params = [];
        if ($serviceType !== '') {
            $sql .= ' AND service_type = ?';
            $params[] = $serviceType;
        }
        if ($search !== '') {
            $sql .= ' AND (service_name LIKE ? OR description LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY price ' . strtoupper($sort);

        return $this->raw($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recommend_within_budget_and_capacity($maximumBudget, $guests)
    {
        return $this->raw(
            "SELECT service_id AS id, service_name AS name, service_type, description, price, capacity
             FROM services
             WHERE is_active = 1 AND price <= ? AND capacity >= ?
             ORDER BY price ASC",
            [$maximumBudget, $guests]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find_active($serviceId)
    {
        return $this->raw(
            "SELECT service_id AS id, service_name AS name, service_type, description, price, capacity
             FROM services
             WHERE service_id = ? AND is_active = 1
             LIMIT 1",
            [$serviceId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function list_for_admin()
    {
        return $this->raw(
            "SELECT service_id AS id, service_name AS name, service_type, description,
                    price, capacity, image_url, is_active
             FROM services
             ORDER BY service_id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
