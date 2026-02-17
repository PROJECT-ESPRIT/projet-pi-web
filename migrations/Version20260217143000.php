<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add family booking pricing columns to reservation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD quantite INT NOT NULL DEFAULT 1, ADD prix_unitaire DOUBLE PRECISION NOT NULL DEFAULT 0, ADD remise_rate DOUBLE PRECISION NOT NULL DEFAULT 0, ADD montant_total DOUBLE PRECISION NOT NULL DEFAULT 0');
        $this->addSql('UPDATE reservation r INNER JOIN evenement e ON e.id = r.evenement_id SET r.quantite = 1, r.prix_unitaire = COALESCE(e.prix, 0), r.remise_rate = 0, r.montant_total = COALESCE(e.prix, 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP quantite, DROP prix_unitaire, DROP remise_rate, DROP montant_total');
    }
}
