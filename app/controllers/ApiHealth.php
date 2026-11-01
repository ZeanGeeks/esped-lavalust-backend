<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/ApiController.php';

class ApiHealth extends ApiController
{
    public function health()
    {
        if ($this->method_not_allowed('GET, OPTIONS')) {
            return;
        }

        $this->respond([
            'status' => 'ok',
            'service' => 'esped-resort-api',
            'timestamp' => gmdate('c'),
        ]);
    }

}
