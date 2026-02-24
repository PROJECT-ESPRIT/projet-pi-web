<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223185031 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create forum likes and signalements tables';
    }

    public function up(Schema $schema): void
    {
        // Create forum_like table
        $table = $schema->createTable('forum_like');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('forum_id', 'integer', ['notnull' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['forum_id', 'user_id'], 'uniq_forum_user_like');
        $table->addForeignKeyConstraint('forum', ['forum_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addForeignKeyConstraint('`user`', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

        // Create forum_signalement table
        $table = $schema->createTable('forum_signalement');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('forum_id', 'integer', ['notnull' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['forum_id', 'user_id'], 'uniq_forum_user_signalement');
        $table->addForeignKeyConstraint('forum', ['forum_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addForeignKeyConstraint('`user`', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

        // Create forum_reponse_like table
        $table = $schema->createTable('forum_reponse_like');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('reponse_id', 'integer', ['notnull' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['reponse_id', 'user_id'], 'uniq_reponse_user_like');
        $table->addForeignKeyConstraint('forum_reponse', ['reponse_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addForeignKeyConstraint('`user`', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

        // Create forum_reponse_signalement table
        $table = $schema->createTable('forum_reponse_signalement');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('reponse_id', 'integer', ['notnull' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['reponse_id', 'user_id'], 'uniq_reponse_user_signalement');
        $table->addForeignKeyConstraint('forum_reponse', ['reponse_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addForeignKeyConstraint('`user`', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        // Drop tables
        $schema->dropTable('forum_like');
        $schema->dropTable('forum_signalement');
        $schema->dropTable('forum_reponse_like');
        $schema->dropTable('forum_reponse_signalement');
    }
}
