<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602282135 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add charity entity and link each donation to one charity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE charity (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        $this->addSql("INSERT INTO charity (name, description, created_at) VALUES ('General Charity', 'Default charity used for legacy donations.', NOW())");

        $this->addSql('ALTER TABLE donation ADD charity_id INT DEFAULT NULL');
        $this->addSql('UPDATE donation SET charity_id = (SELECT MIN(id) FROM charity) WHERE charity_id IS NULL');
        $this->addSql('ALTER TABLE donation MODIFY charity_id INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_DONATION_CHARITY ON donation (charity_id)');
        $this->addSql('ALTER TABLE donation ADD CONSTRAINT FK_DONATION_CHARITY FOREIGN KEY (charity_id) REFERENCES charity (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE donation DROP FOREIGN KEY FK_DONATION_CHARITY');
        $this->addSql('DROP INDEX IDX_DONATION_CHARITY ON donation');
        $this->addSql('ALTER TABLE donation DROP charity_id');
        $this->addSql('DROP TABLE charity');
    }
}
