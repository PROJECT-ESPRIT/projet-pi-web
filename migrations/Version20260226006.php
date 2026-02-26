<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create charity table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE charity (
            id INT AUTO_INCREMENT NOT NULL,
            created_by_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            picture VARCHAR(255) DEFAULT NULL,
            minimum_amount DOUBLE PRECISION DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL,
            description LONGTEXT DEFAULT NULL,
            INDEX IDX_837DB71EB03A8386 (created_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE charity ADD CONSTRAINT FK_837DB71EB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity DROP FOREIGN KEY FK_837DB71EB03A8386');
        $this->addSql('DROP TABLE charity');
    }
}
