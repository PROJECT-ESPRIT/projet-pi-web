<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create charity table and link donation to charity with optional amount';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE charity (id INT AUTO_INCREMENT NOT NULL, created_by_id INT NOT NULL, title VARCHAR(255) NOT NULL, picture VARCHAR(255) DEFAULT NULL, minimum_amount DOUBLE PRECISION DEFAULT NULL, status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', created_at DATETIME NOT NULL, INDEX IDX_CHARITY_CREATED_BY (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE donation ADD charity_id INT DEFAULT NULL, ADD amount DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_DONATION_CHARITY ON donation (charity_id)');
        $this->addSql('ALTER TABLE charity ADD CONSTRAINT FK_CHARITY_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE donation ADD CONSTRAINT FK_DONATION_CHARITY FOREIGN KEY (charity_id) REFERENCES charity (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE donation DROP FOREIGN KEY FK_DONATION_CHARITY');
        $this->addSql('ALTER TABLE charity DROP FOREIGN KEY FK_CHARITY_CREATED_BY');
        $this->addSql('DROP TABLE charity');
        $this->addSql('DROP INDEX IDX_DONATION_CHARITY ON donation');
        $this->addSql('ALTER TABLE donation DROP charity_id, DROP amount');
    }
}
