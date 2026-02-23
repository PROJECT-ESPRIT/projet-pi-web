<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223195800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create forum_post_score table for scoring system';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('forum_post_score');
        
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('forum_id', 'integer', ['notNull' => true]);
        $table->addColumn('calculated_score', 'decimal', ['precision' => 10, 'scale' => 4, 'default' => '0.0000']);
        $table->addColumn('likes_count', 'integer', ['default' => 0]);
        $table->addColumn('dislikes_count', 'integer', ['default' => 0]);
        $table->addColumn('comments_count', 'integer', ['default' => 0]);
        $table->addColumn('views_count', 'integer', ['default' => 0]);
        $table->addColumn('base_score', 'decimal', ['precision' => 10, 'scale' => 4, 'default' => '1.0000']);
        $table->addColumn('last_calculated_at', 'datetime', ['notNull' => true]);
        $table->addColumn('last_activity_at', 'datetime', ['notNull' => true]);
        $table->addColumn('created_at', 'datetime', ['notNull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notNull' => true]);
        
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['forum_id'], 'unique_forum');
        $table->addIndex(['calculated_score'], 'idx_calculated_score');
        $table->addIndex(['last_activity_at'], 'idx_last_activity');
        $table->addIndex(['last_calculated_at'], 'idx_last_calculated');
        
        $table->addForeignKeyConstraint('fk_forum_post_score_forum', 'forum', 'id', 'forum_id', ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('forum_post_score');
    }
}
