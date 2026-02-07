<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create TypeDon and Donation tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE type_don (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE donation (id INT AUTO_INCREMENT NOT NULL, type_id INT NOT NULL, donateur_id INT NOT NULL, description LONGTEXT DEFAULT NULL, date_don DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_JC94819C54C8C93 (type_id), INDEX IDX_JC94819C83A9843 (donateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE donation ADD CONSTRAINT FK_JC94819C54C8C93 FOREIGN KEY (type_id) REFERENCES type_don (id)');
        $this->addSql('ALTER TABLE donation ADD CONSTRAINT FK_JC94819C83A9843 FOREIGN KEY (donateur_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE donation DROP FOREIGN KEY FK_JC94819C54C8C93');
        $this->addSql('ALTER TABLE donation DROP FOREIGN KEY FK_JC94819C83A9843');
        $this->addSql('DROP TABLE donation');
        $this->addSql('DROP TABLE type_don');
    }
}
