<?php

class Create_booking_tables {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function up()
    {
        $this->_lava->db->raw("
            CREATE TABLE IF NOT EXISTS bookings (
                booking_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                approved_by_admin_id INT UNSIGNED DEFAULT NULL,
                reference_no VARCHAR(30) NOT NULL,
                check_in_date DATE NOT NULL,
                check_out_date DATE NOT NULL,
                guest_count INT UNSIGNED NOT NULL DEFAULT 1,
                status ENUM('Pending','Confirmed','Cancelled','Completed') NOT NULL DEFAULT 'Pending',
                total_amount DECIMAL(10,2) NOT NULL DEFAULT '0.00',
                special_requests TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (booking_id),
                UNIQUE KEY reference_no (reference_no),
                KEY fk_booking_user (user_id),
                KEY fk_booking_admin (approved_by_admin_id),
                CONSTRAINT fk_booking_admin FOREIGN KEY (approved_by_admin_id)
                    REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_booking_user FOREIGN KEY (user_id)
                    REFERENCES users (user_id) ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $this->_lava->db->raw("
            CREATE TABLE IF NOT EXISTS booking_items (
                booking_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id INT UNSIGNED NOT NULL,
                service_id INT UNSIGNED NOT NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                unit_price DECIMAL(10,2) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                PRIMARY KEY (booking_item_id),
                KEY fk_item_booking (booking_id),
                KEY fk_item_service (service_id),
                CONSTRAINT fk_item_booking FOREIGN KEY (booking_id)
                    REFERENCES bookings (booking_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_item_service FOREIGN KEY (service_id)
                    REFERENCES services (service_id) ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    public function down()
    {
        $this->_lava->db->raw("DROP TABLE IF EXISTS booking_items");
        $this->_lava->db->raw("DROP TABLE IF EXISTS bookings");
    }
}
