<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220140041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande CHANGE date_commande date_commande DATETIME NOT NULL');
        $this->addSql('ALTER TABLE donation CHANGE date_don date_don DATETIME NOT NULL');
        $this->addSql('ALTER TABLE donation RENAME INDEX idx_jc94819c54c8c93 TO IDX_31E581A0C54C8C93');
        $this->addSql('ALTER TABLE donation RENAME INDEX idx_jc94819c83a9843 TO IDX_31E581A0A9C80E3');
        $this->addSql('ALTER TABLE evenement CHANGE date_debut date_debut DATETIME DEFAULT NULL, CHANGE date_fin date_fin DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE forum CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE forum_reponse CHANGE date_reponse date_reponse DATETIME NOT NULL');
        $this->addSql('ALTER TABLE forum_reponse RENAME INDEX idx_280a49b629ccbad0 TO IDX_AE7A93B629CCBAD0');
        $this->addSql('ALTER TABLE forum_reponse RENAME INDEX idx_280a49b66c759d3 TO IDX_AE7A93B660BB6FE6');
        $this->addSql('ALTER TABLE password_reset_token CHANGE expires_at expires_at DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE used_at used_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE password_reset_token RENAME INDEX uniq_7268fa5e58af0efc TO UNIQ_6B7BA4B6B3BC57DA');
        $this->addSql('ALTER TABLE password_reset_token RENAME INDEX idx_7268fa5ea76ed395 TO IDX_6B7BA4B6A76ED395');
        $this->addSql('ALTER TABLE reservation DROP quantite, DROP prix_unitaire, DROP remise_rate, DROP montant_total, CHANGE date_reservation date_reservation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user DROP points, DROP loyalty_level');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE password_reset_token CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE used_at used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE password_reset_token RENAME INDEX idx_6b7ba4b6a76ed395 TO IDX_7268FA5EA76ED395');
        $this->addSql('ALTER TABLE password_reset_token RENAME INDEX uniq_6b7ba4b6b3bc57da TO UNIQ_7268FA5E58AF0EFC');
        $this->addSql('ALTER TABLE `user` ADD points INT DEFAULT 0 NOT NULL, ADD loyalty_level VARCHAR(20) DEFAULT \'BRONZE\' NOT NULL');
        $this->addSql('ALTER TABLE donation CHANGE date_don date_don DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE donation RENAME INDEX idx_31e581a0a9c80e3 TO IDX_JC94819C83A9843');
        $this->addSql('ALTER TABLE donation RENAME INDEX idx_31e581a0c54c8c93 TO IDX_JC94819C54C8C93');
        $this->addSql('ALTER TABLE commande CHANGE date_commande date_commande DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE forum CHANGE date_creation date_creation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE evenement CHANGE date_debut date_debut DATETIME NOT NULL, CHANGE date_fin date_fin DATETIME NOT NULL');
        $this->addSql('ALTER TABLE forum_reponse CHANGE date_reponse date_reponse DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE forum_reponse RENAME INDEX idx_ae7a93b660bb6fe6 TO IDX_280A49B66C759D3');
        $this->addSql('ALTER TABLE forum_reponse RENAME INDEX idx_ae7a93b629ccbad0 TO IDX_280A49B629CCBAD0');
        $this->addSql('ALTER TABLE reservation ADD quantite INT DEFAULT 1 NOT NULL, ADD prix_unitaire DOUBLE PRECISION DEFAULT \'0\' NOT NULL, ADD remise_rate DOUBLE PRECISION DEFAULT \'0\' NOT NULL, ADD montant_total DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE date_reservation date_reservation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
