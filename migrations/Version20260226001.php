<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE `user` (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            nom VARCHAR(255) NOT NULL,
            prenom VARCHAR(255) NOT NULL,
            telephone VARCHAR(20) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'EMAIL_PENDING',
            email_verification_token VARCHAR(64) DEFAULT NULL,
            email_verification_sent_at DATETIME DEFAULT NULL,
            date_naissance DATETIME DEFAULT NULL,
            profile_image_url VARCHAR(500) DEFAULT NULL,
            UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `user`');
    }
}
