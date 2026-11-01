<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class User extends Model
{
    protected $table = 'users';
    protected $primary_key = 'id';
    protected $fillable = [];
    protected $guarded = ['id'];

    private function add_is_active($user)
    {
        if (!$user) {
            return $user;
        }

        $user['is_active'] = (int) ($user['account_status'] === 'active');
        unset($user['account_status']);

        return $user;
    }

    public function find_by_id($userId)
    {
        $user = $this->db->table($this->table)
            ->select('id, full_name AS username, email, role, password_hash AS password, account_status')
            ->where('id', $userId)
            ->limit(1)
            ->get();

        return $this->add_is_active($user);
    }

    public function find_for_login($email)
    {
        $user = $this->db->table($this->table)
            ->select('id, full_name AS username, email, role, password_hash AS password, account_status')
            ->where('email', $email)
            ->limit(1)
            ->get();

        return $this->add_is_active($user);
    }

    public function find_notification_recipient($userId)
    {
        return $this->db->table($this->table)
            ->select('id, full_name AS username, email')
            ->where('id', $userId)
            ->limit(1)
            ->get();
    }

    public function email_is_used_by_another_user($email, $userId)
    {
        return $this->db->table($this->table)
            ->select('id')
            ->where('email', $email)
            ->where('id', '<>', $userId)
            ->limit(1)
            ->get();
    }

    public function find_password($userId)
    {
        return $this->db->table($this->table)
            ->select('password_hash AS password')
            ->where('id', $userId)
            ->limit(1)
            ->get();
    }

    public function list_guests($search)
    {
        $query = $this->db->table($this->table)
            ->select('id, full_name AS username, email, account_status, created_at')
            ->where('role', 'guest');

        if ($search !== '') {
            $query->grouped(function ($query) use ($search) {
                $query->like('full_name', '%' . $search . '%')
                    ->or_like('email', '%' . $search . '%');
            });
        }

        $guests = $query->order_by('created_at', 'DESC')->get_all();
        foreach ($guests as &$guest) {
            $guest = $this->add_is_active($guest);
        }
        unset($guest);

        return $guests;
    }
}