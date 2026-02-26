<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create forum and forum_reponse tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE forum (
            id INT AUTO_INCREMENT NOT NULL,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(180) NOT NULL,
            sujet VARCHAR(100) NOT NULL,
            message LONGTEXT NOT NULL,
            date_creation DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");

        $this->addSql("CREATE TABLE forum_reponse (
            id INT AUTO_INCREMENT NOT NULL,
            forum_id INT NOT NULL,
            auteur_id INT NOT NULL,
            contenu LONGTEXT NOT NULL,
            date_reponse DATETIME NOT NULL,
            INDEX IDX_AE7A93B629CCBAD0 (forum_id),
            INDEX IDX_AE7A93B660BB6FE6 (auteur_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE forum_reponse ADD CONSTRAINT FK_AE7A93B629CCBAD0 FOREIGN KEY (forum_id) REFERENCES forum (id)');
        $this->addSql('ALTER TABLE forum_reponse ADD CONSTRAINT FK_AE7A93B660BB6FE6 FOREIGN KEY (auteur_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_reponse DROP FOREIGN KEY FK_AE7A93B629CCBAD0');
        $this->addSql('ALTER TABLE forum_reponse DROP FOREIGN KEY FK_AE7A93B660BB6FE6');
        $this->addSql('DROP TABLE forum_reponse');
        $this->addSql('DROP TABLE forum');
    }
}
