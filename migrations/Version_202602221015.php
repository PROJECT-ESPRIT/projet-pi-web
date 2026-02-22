<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602221015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event cancellation fields: annule, motif_annulation, date_annulation';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        $db = method_exists($conn, 'getDatabase') ? $conn->getDatabase() : ($conn->getParams()['dbname'] ?? '');
        $exists = $conn->fetchOne(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'evenement' AND COLUMN_NAME = 'annule'",
            [$db],
            [ParameterType::STRING]
        );
        if (!$exists) {
            $this->addSql('ALTER TABLE evenement ADD annule TINYINT(1) DEFAULT 0 NOT NULL, ADD motif_annulation LONGTEXT DEFAULT NULL, ADD date_annulation DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP annule, DROP motif_annulation, DROP date_annulation');
    }
}
