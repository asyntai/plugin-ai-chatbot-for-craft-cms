<?php

namespace Asyntai\Chatbot\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%asyntai_store}}')) {
            $this->createTable('{{%asyntai_store}}', [
                'id' => $this->primaryKey(),
                'name' => $this->string(64)->notNull(),
                'value' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
            $this->createIndex('asyntai_store_name_unq', '{{%asyntai_store}}', ['name'], true);
        }
        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%asyntai_store}}');
        return true;
    }
}
