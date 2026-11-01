<?php

class Add_email_notification_details
{
    private $_lava;

    private $columns = [
        'recipient_email' => 'VARCHAR(150) DEFAULT NULL',
        'subject' => 'VARCHAR(255) DEFAULT NULL',
        'body' => 'TEXT DEFAULT NULL',
        'error_message' => 'TEXT DEFAULT NULL',
        'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function up()
    {
        $existingColumns = $this->get_columns();

        foreach ($this->columns as $name => $definition) {
            if (!in_array($name, $existingColumns, true)) {
                $this->_lava->db->raw("ALTER TABLE email_notifications ADD COLUMN {$name} {$definition}");
            }
        }
    }

    public function down()
    {
        $existingColumns = $this->get_columns();
        $columnsToDrop = array_intersect(array_keys($this->columns), $existingColumns);

        if ($columnsToDrop !== []) {
            $this->_lava->db->raw(
                'ALTER TABLE email_notifications DROP COLUMN ' . implode(', DROP COLUMN ', $columnsToDrop)
            );
        }
    }

    private function get_columns()
    {
        return $this->_lava->db->raw(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_notifications'"
        )->fetchAll(PDO::FETCH_COLUMN);
    }
}
