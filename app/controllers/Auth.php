<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/ApiController.php';

class Auth extends ApiController
{
    public function register()
    {
        if ($this->method_not_allowed('POST, OPTIONS')) {
            return;
        }

        $data = $this->get_request_data();
        $username = trim((string) ($data['username'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $this->error('Username, valid email, and password (min 8 chars) are required.', 422);
            return;
        }

        $existing = $this->db->table('users')->where('email', $email)->limit(1)->get();
        if ($existing) {
            $this->error('Email already registered.', 409);
            return;
        }

        $this->db->table('users')->insert([
            'full_name' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'guest',
            'account_status' => 'active',
            'phone' => null,
        ]);

        $this->respond(['success' => true, 'message' => 'Guest account created.'], 201);
    }

    public function google()
    {
        if ($this->method_not_allowed('POST, OPTIONS')) {
            return;
        }

        $clientId = trim((string) getenv('GOOGLE_CLIENT_ID'));
        if ($clientId === '') {
            $this->error('Google sign-in is not configured.', 503);
            return;
        }

        $data = $this->get_request_data();
        $credential = trim((string) ($data['credential'] ?? ''));
        if ($credential === '' || strlen($credential) > 8192) {
            $this->error('A valid Google credential is required.', 422);
            return;
        }

        $curl = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential));
        if ($curl === false) {
            $this->error('Google identity verification is temporarily unavailable.', 503);
            return;
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $requestFailed = $response === false;
        curl_close($curl);

        if ($requestFailed) {
            $this->error('Google identity verification is temporarily unavailable.', 503);
            return;
        }

        $identity = json_decode($response, true);
        $issuer = is_array($identity) ? (string) ($identity['iss'] ?? '') : '';
        $email = is_array($identity) ? trim((string) ($identity['email'] ?? '')) : '';
        $emailVerified = is_array($identity) && filter_var(
            $identity['email_verified'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        if (
            $status !== 200
            || !is_array($identity)
            || !in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)
            || (string) ($identity['aud'] ?? '') !== $clientId
            || (int) ($identity['exp'] ?? 0) <= time()
            || empty($identity['sub'])
            || !$emailVerified
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $this->error('Invalid or expired Google credential.', 401);
            return;
        }

        $user = $this->User->find_for_login($email);
        if (!$user) {
            $name = trim((string) ($identity['name'] ?? ''));
            if ($name === '') {
                $name = strstr($email, '@', true) ?: $email;
            }

            $this->db->table('users')->insert([
                'full_name' => substr($name, 0, 100),
                'email' => $email,
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'role' => 'guest',
                'account_status' => 'active',
                'phone' => null,
            ]);
            $user = $this->User->find_for_login($email);
        }

        if (!$user || (int) $user['is_active'] !== 1) {
            $this->error('Account is suspended.', 403);
            return;
        }

        $tokens = $this->issue_tokens($user);
        $this->respond([
            'success' => true,
            'tokens' => $tokens,
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ]);
    }

    public function login()
    {
        if ($this->method_not_allowed('POST, OPTIONS')) {
            return;
        }

        $data = $this->get_request_data();
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $user = $this->User->find_for_login($email);
        if (!$user || !password_verify($password, $user['password'])) {
            $this->error('Invalid credentials.', 401);
            return;
        }

        if ((int) $user['is_active'] !== 1) {
            $this->error('Account is suspended.', 403);
            return;
        }

        $tokens = $this->issue_tokens($user);
        $this->respond([
            'success' => true,
            'tokens' => $tokens,
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ]);
    }

    public function logout()
    {
        if ($this->method_not_allowed('POST, OPTIONS')) {
            return;
        }
        $data = $this->get_request_data();
        $refreshToken = (string) ($data['refresh_token'] ?? '');
        if ($refreshToken !== '' && $this->validate_refresh_token($refreshToken)) {
            $this->revoke_refresh_token($refreshToken);
        }
        $this->respond(['success' => true, 'message' => 'Logged out.']);
    }

    public function refresh()
    {
        if ($this->method_not_allowed('POST, OPTIONS')) {
            return;
        }

        $data = $this->get_request_data();
        $refreshToken = (string) ($data['refresh_token'] ?? '');
        $payload = $this->validate_refresh_token($refreshToken);
        $user = $payload ? $this->find_token_user($payload) : null;
        if (!$payload || !$user || (int) $user['is_active'] !== 1) {
            $this->error('Invalid or expired refresh token.', 401);
            return;
        }

        $this->db->transaction();
        try {
            if (!$this->consume_refresh_token($refreshToken, (int) $user['id'])) {
                $this->db->roll_back();
                $this->error('Refresh token was already used or revoked.', 401);
                return;
            }
            $tokens = $this->issue_tokens($user);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->roll_back();
            throw $exception;
        }

        $this->respond([
            'success' => true,
            'tokens' => $tokens,
        ]);
    }

    public function me()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }
        $user = $this->current_user();
        $this->respond([
            'authenticated' => (bool) $user,
            'user' => $user ? [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
            ] : null,
        ]);
    }

    public function update_account()
    {
        if ($this->method_not_allowed('PUT, OPTIONS')) {
            return;
        }
        $user = $this->require_auth('guest');
        if (!$user) {
            return;
        }

        $data = $this->get_request_data();
        $username = trim((string) ($data['username'] ?? $user['username']));
        $email = trim((string) ($data['email'] ?? $user['email']));
        if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid account details.', 422);
            return;
        }

        $duplicate = $this->User->email_is_used_by_another_user($email, (int) $user['id']);
        if ($duplicate) {
            $this->error('Email is already in use.', 409);
            return;
        }

        $this->db->table('users')->where('id', (int) $user['id'])->update([
            'full_name' => $username,
            'email' => $email,
        ]);

        $this->respond(['success' => true, 'message' => 'Account updated.']);
    }

    public function update_password()
    {
        if ($this->method_not_allowed('PUT, OPTIONS')) {
            return;
        }
        $user = $this->require_auth('guest');
        if (!$user) {
            return;
        }

        $data = $this->get_request_data();
        $current = (string) ($data['current_password'] ?? '');
        $next = (string) ($data['new_password'] ?? '');
        $fullUser = $this->User->find_password((int) $user['id']);
        if (!$fullUser || !password_verify($current, $fullUser['password'])) {
            $this->error('Current password is incorrect.', 422);
            return;
        }
        if (strlen($next) < 8) {
            $this->error('New password must be at least 8 characters.', 422);
            return;
        }

        $this->db->table('users')->where('id', (int) $user['id'])->update([
            'password_hash' => password_hash($next, PASSWORD_DEFAULT),
        ]);
        $this->respond(['success' => true, 'message' => 'Password updated.']);
    }
}
