<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align Symfony Forum entity with JavaFX-canonical forum_topic table. Drop Symfony-only forum table (was empty), re-point forum_reponse.forum_id FK to forum_topic.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE forum_reponse DROP FOREIGN KEY FK_AE7A93B629CCBAD0");
        $this->addSql("DROP TABLE IF EXISTS forum");
        $this->addSql("ALTER TABLE forum_reponse
            ADD CONSTRAINT FK_AE7A93B629CCBAD0_FT FOREIGN KEY (forum_id) REFERENCES forum_topic(id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE forum_reponse DROP FOREIGN KEY FK_AE7A93B629CCBAD0_FT");
        $this->addSql("CREATE TABLE IF NOT EXISTS forum (
            id INT AUTO_INCREMENT NOT NULL,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(180) NOT NULL,
            sujet VARCHAR(100) NOT NULL,
            message LONGTEXT NOT NULL,
            date_creation DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");
        $this->addSql("ALTER TABLE forum_reponse
            ADD CONSTRAINT FK_AE7A93B629CCBAD0 FOREIGN KEY (forum_id) REFERENCES forum(id) ON DELETE CASCADE");
    }
}
