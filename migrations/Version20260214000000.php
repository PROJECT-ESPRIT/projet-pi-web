<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Forum and ForumReponse tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE forum (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, sujet VARCHAR(100) NOT NULL, message LONGTEXT NOT NULL, date_creation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forum_reponse (id INT AUTO_INCREMENT NOT NULL, forum_id INT NOT NULL, auteur_id INT NOT NULL, contenu LONGTEXT NOT NULL, date_reponse DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A39F0F42F45F6F78 (forum_id), INDEX IDX_A39F0F4260BB6FE6 (auteur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE forum_reponse ADD CONSTRAINT FK_A39F0F42F45F6F78 FOREIGN KEY (forum_id) REFERENCES forum (id)');
        $this->addSql('ALTER TABLE forum_reponse ADD CONSTRAINT FK_A39F0F4260BB6FE6 FOREIGN KEY (auteur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD forum_reponses_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6498B8E7F1C FOREIGN KEY (forum_reponses_id) REFERENCES forum_reponse (id)');
        $this->addSql('CREATE INDEX IDX_8D93D6498B8E7F1C ON user (forum_reponses_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6498B8E7F1C');
        $this->addSql('DROP INDEX IDX_8D93D6498B8E7F1C ON user');
        $this->addSql('ALTER TABLE forum_reponse DROP FOREIGN KEY FK_A39F0F42F45F6F78');
        $this->addSql('ALTER TABLE forum_reponse DROP FOREIGN KEY FK_A39F0F4260BB6FE6');
        $this->addSql('DROP TABLE forum_reponse');
        $this->addSql('DROP TABLE forum');
        $this->addSql('ALTER TABLE user DROP forum_reponses_id');
    }
}
