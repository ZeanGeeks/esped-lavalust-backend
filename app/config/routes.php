<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/
/** @var object $router **/

$router->get('/', 'Welcome::index');
$router->get('/api/health', 'Welcome::health');

$router->get('/api/v1/health', 'ApiHealth::health');

$router->post('/api/v1/auth/register', 'Auth::register');
$router->post('/api/v1/auth/login', 'Auth::login');
$router->post('/api/v1/auth/google', 'Auth::google');
$router->post('/api/v1/auth/refresh', 'Auth::refresh');
$router->post('/api/v1/auth/logout', 'Auth::logout');
$router->get('/api/v1/auth/me', 'Auth::me');
$router->put('/api/v1/auth/account', 'Auth::update_account');
$router->put('/api/v1/auth/password', 'Auth::update_password');

$router->get('/api/v1/home', 'PublicApi::home');
$router->get('/api/v1/resort-info', 'PublicApi::resort_info');
$router->get('/api/v1/services', 'PublicApi::services');
$router->get('/api/v1/services/{id}/availability', 'PublicApi::service_availability');
$router->post('/api/v1/recommendation', 'PublicApi::recommendation');

$router->post('/api/v1/bookings', 'BookingApi::create_booking');
$router->get('/api/v1/bookings/my', 'BookingApi::my_bookings');
$router->delete('/api/v1/bookings/{id}/cancel', 'BookingApi::cancel_my_booking');
$router->post('/api/v1/bookings/{id}/feedback', 'BookingApi::submit_feedback');

$router->get('/api/v1/admin/dashboard', 'AdminApi::admin_dashboard');
$router->get('/api/v1/admin/bookings', 'AdminApi::admin_bookings');
$router->put('/api/v1/admin/bookings/{id}/confirm', 'AdminApi::admin_confirm_booking');
$router->put('/api/v1/admin/bookings/{id}/reject', 'AdminApi::admin_reject_booking');
$router->put('/api/v1/admin/bookings/{id}/complete', 'AdminApi::admin_complete_booking');
$router->get('/api/v1/admin/bookings/calendar', 'AdminApi::admin_booking_calendar');

$router->get('/api/v1/admin/services', 'AdminApi::admin_services');
$router->post('/api/v1/admin/services', 'AdminApi::admin_services');
$router->put('/api/v1/admin/services/{id}', 'AdminApi::admin_update_service');
$router->delete('/api/v1/admin/services/{id}', 'AdminApi::admin_deactivate_service');
$router->get('/api/v1/admin/services/{id}/availability', 'AdminApi::admin_service_availability');
$router->put('/api/v1/admin/services/{id}/availability', 'AdminApi::admin_service_availability');

$router->get('/api/v1/admin/guests', 'AdminApi::admin_guests');
$router->put('/api/v1/admin/guests/{id}/status', 'AdminApi::admin_guest_status');
$router->get('/api/v1/admin/feedback', 'AdminApi::admin_feedback');
$router->get('/api/v1/admin/reports', 'AdminApi::admin_reports');
$router->get('/api/v1/admin/activity-logs', 'AdminApi::admin_activity_logs');
$router->get('/api/v1/admin/settings', 'AdminApi::admin_settings');
$router->put('/api/v1/admin/settings', 'AdminApi::admin_settings');
$router->get('/api/v1/admin/backup/export', 'AdminApi::admin_backup_export');

if (defined('IS_CLI') && IS_CLI) {
    $router->get('/migrate', 'MigrationController::migrate');
    $router->get('/create-migration/{name}', 'MigrationController::create_migration');
    $router->get('/rollback', 'MigrationController::rollback');
    $router->get('/rollback-all', 'MigrationController::rollback_all');
    $router->get('/refresh', 'MigrationController::refresh');
    $router->get('/status', 'MigrationController::status');
}