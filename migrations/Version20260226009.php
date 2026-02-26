<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create commande and ligne_commande tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE commande (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            date_commande DATETIME NOT NULL,
            statut VARCHAR(255) NOT NULL,
            total DOUBLE PRECISION NOT NULL,
            INDEX IDX_6EEAA67DA76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');

        $this->addSql("CREATE TABLE ligne_commande (
            id INT AUTO_INCREMENT NOT NULL,
            commande_id INT NOT NULL,
            produit_id INT NOT NULL,
            quantite INT NOT NULL,
            prix_unitaire DOUBLE PRECISION NOT NULL,
            INDEX IDX_3170B74B82EA2E54 (commande_id),
            INDEX IDX_3170B74BF347EFB (produit_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74B82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74BF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B82EA2E54');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74BF347EFB');
        $this->addSql('DROP TABLE ligne_commande');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DA76ED395');
        $this->addSql('DROP TABLE commande');
    }
}
