<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221164000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Require donor for every donation';
    }

    public function up(Schema $schema): void
    {
        $nullDonors = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM donation WHERE donateur_id IS NULL');
        $this->abortIf(
            $nullDonors > 0,
            'Migration aborted: there are donations without donor (donateur_id IS NULL). Please fix data first.'
        );

        $this->addSql('ALTER TABLE donation CHANGE donateur_id donateur_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE donation CHANGE donateur_id donateur_id INT DEFAULT NULL');
    }
}
