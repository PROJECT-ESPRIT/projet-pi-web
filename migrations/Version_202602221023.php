<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602221023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sync schema with entity mappings (index renames and column defaults)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity DROP INDEX idx_charity_created_by, ADD INDEX IDX_837DB71EB03A8386 (created_by_id)');
        $this->addSql('ALTER TABLE donation DROP INDEX idx_donation_charity, ADD INDEX IDX_31E581A0F5C97E37 (charity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity DROP INDEX IDX_837DB71EB03A8386, ADD INDEX idx_charity_created_by (created_by_id)');
        $this->addSql('ALTER TABLE donation DROP INDEX IDX_31E581A0F5C97E37, ADD INDEX idx_donation_charity (charity_id)');
    }
}
