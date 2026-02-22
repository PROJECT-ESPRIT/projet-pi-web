<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Stripe fields to reservation (stripe_checkout_session_id, amount_paid)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD stripe_checkout_session_id VARCHAR(255) DEFAULT NULL, ADD amount_paid INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP stripe_checkout_session_id, DROP amount_paid');
    }
}
