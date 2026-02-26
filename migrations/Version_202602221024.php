<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602221024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align column definitions with Doctrine entity mappings (MariaDB compatibility)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE charity CHANGE picture picture VARCHAR(255) DEFAULT NULL, CHANGE minimum_amount minimum_amount DOUBLE PRECISION DEFAULT NULL, CHANGE status status VARCHAR(20) DEFAULT 'ACTIVE' NOT NULL");
        $this->addSql('ALTER TABLE donation CHANGE amount amount DOUBLE PRECISION DEFAULT NULL, CHANGE photo photo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE evenement CHANGE date_debut date_debut DATETIME DEFAULT NULL, CHANGE date_fin date_fin DATETIME DEFAULT NULL, CHANGE prix prix DOUBLE PRECISION DEFAULT NULL, CHANGE image image VARCHAR(255) DEFAULT NULL, CHANGE layout_type layout_type VARCHAR(20) DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE date_annulation date_annulation DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE password_reset_token CHANGE used_at used_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE produit CHANGE image image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation CHANGE seat_label seat_label VARCHAR(20) DEFAULT NULL, CHANGE stripe_checkout_session_id stripe_checkout_session_id VARCHAR(255) DEFAULT NULL, CHANGE scanned_at scanned_at DATETIME DEFAULT NULL');
        $this->addSql("ALTER TABLE user CHANGE roles roles JSON NOT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE status status VARCHAR(20) DEFAULT 'EMAIL_PENDING' NOT NULL, CHANGE email_verification_token email_verification_token VARCHAR(64) DEFAULT NULL, CHANGE email_verification_sent_at email_verification_sent_at DATETIME DEFAULT NULL, CHANGE date_naissance date_naissance DATETIME DEFAULT NULL, CHANGE profile_image_url profile_image_url VARCHAR(500) DEFAULT NULL");
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
