<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class User extends Model
{
    protected $table = 'users';
    protected $primary_key = 'user_id';
    protected $fillable = [];
    protected $guarded = ['user_id'];

    public function find_for_session($userId)
    {
        return $this->raw(
            "SELECT user_id AS id, full_name AS username, email, role,
                    password_hash AS password,
                    CASE WHEN account_status = 'active' THEN 1 ELSE 0 END AS is_active
             FROM users
             WHERE user_id = ?
             LIMIT 1",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function find_for_login($email)
    {
        return $this->raw(
            "SELECT user_id AS id, full_name AS username, email, role,
                    password_hash AS password,
                    CASE WHEN account_status = 'active' THEN 1 ELSE 0 END AS is_active
             FROM users
             WHERE email = ?
             LIMIT 1",
            [$email]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function find_notification_recipient($userId)
    {
        return $this->raw(
            'SELECT user_id AS id, full_name AS username, email FROM users WHERE user_id = ? LIMIT 1',
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function email_is_used_by_another_user($email, $userId)
    {
        return $this->raw(
            'SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1',
            [$email, $userId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function find_password($userId)
    {
        return $this->raw(
            'SELECT password_hash AS password FROM users WHERE user_id = ? LIMIT 1',
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    public function list_guests($search)
    {
        $sql = "SELECT user_id AS id, full_name AS username, email,
                       CASE WHEN account_status = 'active' THEN 1 ELSE 0 END AS is_active,
                       created_at
                FROM users
                WHERE role = 'guest'";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (full_name LIKE ? OR email LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY created_at DESC';

        return $this->raw($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }
}