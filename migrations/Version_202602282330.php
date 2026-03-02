<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602282330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add owner to charity for member-managed causes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity ADD owner_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_CHARITY_OWNER ON charity (owner_id)');
        $this->addSql('ALTER TABLE charity ADD CONSTRAINT FK_CHARITY_OWNER FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE charity DROP FOREIGN KEY FK_CHARITY_OWNER');
        $this->addSql('DROP INDEX IDX_CHARITY_OWNER ON charity');
        $this->addSql('ALTER TABLE charity DROP owner_id');
    }
}
