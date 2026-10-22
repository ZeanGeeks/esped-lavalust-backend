<?php

class Create_supporting_tables {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function up()
    {
        $this->_lava->db->raw("
            CREATE TABLE IF NOT EXISTS activity_logs (
                activity_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                actor_user_id INT UNSIGNED NOT NULL,
                action VARCHAR(100) NOT NULL,
                entity_type VARCHAR(50) DEFAULT NULL,
                entity_id INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (activity_id),
                KEY fk_log_user (actor_user_id),
                CONSTRAINT fk_log_user FOREIGN KEY (actor_user_id)
                    REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $this->_lava->db->raw("
            CREATE TABLE IF NOT EXISTS email_notifications (
                notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                booking_id INT UNSIGNED DEFAULT NULL,
                notification_type VARCHAR(50) NOT NULL,
                send_status ENUM('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
                sent_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (notification_id),
                KEY fk_mail_user (user_id),
                KEY fk_mail_booking (booking_id),
                CONSTRAINT fk_mail_booking FOREIGN KEY (booking_id)
                    REFERENCES bookings (booking_id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_mail_user FOREIGN KEY (user_id)
                    REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $this->_lava->db->raw("
            CREATE TABLE IF NOT EXISTS feedback (
                feedback_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                service_id INT UNSIGNED NOT NULL,
                booking_id INT UNSIGNED DEFAULT NULL,
                rating TINYINT UNSIGNED NOT NULL,
                comment TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (feedback_id),
                KEY fk_fb_user (user_id),
                KEY fk_fb_service (service_id),
                KEY fk_fb_booking (booking_id),
                CONSTRAINT fk_fb_booking FOREIGN KEY (booking_id)
                    REFERENCES bookings (booking_id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_fb_service FOREIGN KEY (service_id)
                    REFERENCES services (service_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_fb_user FOREIGN KEY (user_id)
                    REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
        ");

        $this->_lava->db->raw("
            CREATE TABLE IF NOT EXISTS recommendation_requests (
                recommendation_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED DEFAULT NULL,
                budget_min DECIMAL(10,2) DEFAULT NULL,
                budget_max DECIMAL(10,2) DEFAULT NULL,
                guest_count INT UNSIGNED DEFAULT NULL,
                preferences TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (recommendation_id),
                KEY fk_rec_user (user_id),
                CONSTRAINT fk_rec_user FOREIGN KEY (user_id)
                    REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $this->_lava->db->raw("
            CREATE TABLE IF NOT EXISTS recommendation_items (
                recommendation_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                recommendation_id INT UNSIGNED NOT NULL,
                service_id INT UNSIGNED NOT NULL,
                match_reason TEXT DEFAULT NULL,
                rank_order INT UNSIGNED NOT NULL DEFAULT 1,
                PRIMARY KEY (recommendation_item_id),
                KEY fk_recitem_rec (recommendation_id),
                KEY fk_recitem_service (service_id),
                CONSTRAINT fk_recitem_rec FOREIGN KEY (recommendation_id)
                    REFERENCES recommendation_requests (recommendation_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_recitem_service FOREIGN KEY (service_id)
                    REFERENCES services (service_id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $this->_lava->db->raw("
            CREATE TABLE IF NOT EXISTS system_settings (
                setting_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT DEFAULT NULL,
                updated_by_user_id INT UNSIGNED DEFAULT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (setting_id),
                UNIQUE KEY setting_key (setting_key),
                KEY fk_set_user (updated_by_user_id),
                CONSTRAINT fk_set_user FOREIGN KEY (updated_by_user_id)
                    REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    public function down()
    {
        $this->_lava->db->raw("DROP TABLE IF EXISTS system_settings");
        $this->_lava->db->raw("DROP TABLE IF EXISTS recommendation_items");
        $this->_lava->db->raw("DROP TABLE IF EXISTS recommendation_requests");
        $this->_lava->db->raw("DROP TABLE IF EXISTS feedback");
        $this->_lava->db->raw("DROP TABLE IF EXISTS email_notifications");
        $this->_lava->db->raw("DROP TABLE IF EXISTS activity_logs");
    }
}
