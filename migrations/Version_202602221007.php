<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602221007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Commande entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE commande (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            date_commande DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            statut VARCHAR(255) NOT NULL,
            total DOUBLE PRECISION NOT NULL,
            INDEX IDX_6EEAA67DA76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DA76ED395');
        $this->addSql('DROP TABLE commande');
    }
}
