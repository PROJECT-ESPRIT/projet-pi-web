<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602221002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Evenement entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE evenement (
            id INT AUTO_INCREMENT NOT NULL,
            organisateur_id INT NOT NULL,
            titre VARCHAR(255) NOT NULL,
            description LONGTEXT NOT NULL,
            date_debut DATETIME NOT NULL,
            date_fin DATETIME NOT NULL,
            lieu VARCHAR(255) NOT NULL,
            nb_places INT NOT NULL,
            age_min INT DEFAULT NULL,
            age_max INT DEFAULT NULL,
            prix DOUBLE PRECISION DEFAULT NULL,
            image VARCHAR(255) DEFAULT NULL,
            layout_type VARCHAR(20) DEFAULT NULL,
            layout_rows INT DEFAULT NULL,
            layout_cols INT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_B26681ED936B2FA (organisateur_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681ED936B2FA FOREIGN KEY (organisateur_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681ED936B2FA');
        $this->addSql('DROP TABLE evenement');
    }
}
