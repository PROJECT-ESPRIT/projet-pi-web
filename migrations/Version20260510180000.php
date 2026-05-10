<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Symfony-only columns to evenement and reservation that JavaFX schema does not have. JavaFX ignores them; Symfony needs them.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE evenement
            ADD COLUMN IF NOT EXISTS layout_type VARCHAR(20) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS layout_rows INT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS layout_cols INT DEFAULT NULL");

        $this->addSql("ALTER TABLE reservation
            ADD COLUMN IF NOT EXISTS stripe_checkout_session_id VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS scanned_at DATETIME DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE evenement
            DROP COLUMN IF EXISTS layout_type,
            DROP COLUMN IF EXISTS layout_rows,
            DROP COLUMN IF EXISTS layout_cols");

        $this->addSql("ALTER TABLE reservation
            DROP COLUMN IF EXISTS stripe_checkout_session_id,
            DROP COLUMN IF EXISTS scanned_at");
    }
}
