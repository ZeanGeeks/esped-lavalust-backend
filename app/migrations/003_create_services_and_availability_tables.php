<?php

class Create_services_and_availability_tables {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function up()
    {
        $this->_lava->db->raw("
            CREATE TABLE IF NOT EXISTS services (
                service_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                service_name VARCHAR(120) NOT NULL,
                service_type VARCHAR(50) NOT NULL,
                description TEXT DEFAULT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT '0.00',
                capacity INT UNSIGNED NOT NULL DEFAULT 1,
                image_url VARCHAR(255) DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (service_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $this->_lava->db->raw("
            CREATE TABLE IF NOT EXISTS service_availability (
                availability_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                service_id INT UNSIGNED NOT NULL,
                available_date DATE NOT NULL,
                total_quantity INT UNSIGNED NOT NULL DEFAULT 1,
                booked_quantity INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (availability_id),
                UNIQUE KEY uq_service_date (service_id, available_date),
                CONSTRAINT fk_avail_service FOREIGN KEY (service_id)
                    REFERENCES services (service_id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    public function down()
    {
        $this->_lava->db->raw("DROP TABLE IF EXISTS service_availability");
        $this->_lava->db->raw("DROP TABLE IF EXISTS services");
    }
}
