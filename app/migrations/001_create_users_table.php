<?php

class Create_users_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('users')) {
            return;
        }

        $this->_lava->db->raw("
            CREATE TABLE users (
                user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(150) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('guest','admin') NOT NULL DEFAULT 'guest',
                phone VARCHAR(20) DEFAULT NULL,
                account_status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id),
                UNIQUE KEY email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('users');
    }
}