<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602221003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reservation entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reservation (
            id INT AUTO_INCREMENT NOT NULL,
            participant_id INT NOT NULL,
            evenement_id INT NOT NULL,
            date_reservation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            status VARCHAR(50) NOT NULL,
            seat_label VARCHAR(20) DEFAULT NULL,
            stripe_checkout_session_id VARCHAR(255) DEFAULT NULL,
            amount_paid INT DEFAULT NULL,
            INDEX IDX_42C849559D1C3019 (participant_id),
            INDEX IDX_42C84955FD02F13 (evenement_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849559D1C3019 FOREIGN KEY (participant_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849559D1C3019');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955FD02F13');
        $this->addSql('DROP TABLE reservation');
    }
}
