<?php

class Rename_users_primary_key_to_id
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function up()
    {
        $columns = $this->_lava->db->raw(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('id', $columns, true) && !in_array('user_id', $columns, true)) {
            return;
        }
        if (!in_array('user_id', $columns, true) || in_array('id', $columns, true)) {
            throw new RuntimeException('Expected users.user_id and no users.id before primary-key migration.');
        }

        $this->_lava->db->raw('ALTER TABLE users RENAME COLUMN user_id TO id');
    }

    public function down()
    {
        $columns = $this->_lava->db->raw(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('user_id', $columns, true) && !in_array('id', $columns, true)) {
            return;
        }
        if (!in_array('id', $columns, true) || in_array('user_id', $columns, true)) {
            throw new RuntimeException('Expected users.id and no users.user_id before primary-key rollback.');
        }

        $this->_lava->db->raw('ALTER TABLE users RENAME COLUMN id TO user_id');
    }
}
