<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add seating layout fields to evenement and seat_label to reservation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement ADD layout_type VARCHAR(20) DEFAULT NULL, ADD layout_rows INT DEFAULT NULL, ADD layout_cols INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD seat_label VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP layout_type, DROP layout_rows, DROP layout_cols');
        $this->addSql('ALTER TABLE reservation DROP seat_label');
    }
}
