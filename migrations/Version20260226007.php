<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create donation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE donation (
            id INT AUTO_INCREMENT NOT NULL,
            type_id INT NOT NULL,
            donateur_id INT NOT NULL,
            charity_id INT DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            date_don DATETIME NOT NULL,
            amount DOUBLE PRECISION DEFAULT NULL,
            photo VARCHAR(255) DEFAULT NULL,
            is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
            INDEX IDX_31E581A0C54C8C93 (type_id),
            INDEX IDX_31E581A0A9C80E3 (donateur_id),
            INDEX IDX_31E581A0F5C97E37 (charity_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE donation ADD CONSTRAINT FK_31E581A0C54C8C93 FOREIGN KEY (type_id) REFERENCES type_don (id)');
        $this->addSql('ALTER TABLE donation ADD CONSTRAINT FK_31E581A0A9C80E3 FOREIGN KEY (donateur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE donation ADD CONSTRAINT FK_31E581A0F5C97E37 FOREIGN KEY (charity_id) REFERENCES charity (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE donation DROP FOREIGN KEY FK_31E581A0C54C8C93');
        $this->addSql('ALTER TABLE donation DROP FOREIGN KEY FK_31E581A0A9C80E3');
        $this->addSql('ALTER TABLE donation DROP FOREIGN KEY FK_31E581A0F5C97E37');
        $this->addSql('DROP TABLE donation');
    }
}
