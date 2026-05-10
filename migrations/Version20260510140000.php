<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Symfony-only user columns (email_verification_token, email_verification_sent_at, date_naissance) on the shared artconnect schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user`
            ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(64) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS email_verification_sent_at DATETIME DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS date_naissance DATETIME DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user`
            DROP COLUMN IF EXISTS email_verification_token,
            DROP COLUMN IF EXISTS email_verification_sent_at,
            DROP COLUMN IF EXISTS date_naissance");
    }
}
