<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_202602221016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalise column types and rename indexes after initial schema creation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande CHANGE date_commande date_commande DATETIME NOT NULL');
        $this->addSql('ALTER TABLE donation CHANGE date_don date_don DATETIME NOT NULL');
        $this->addSql('ALTER TABLE donation DROP INDEX idx_jc94819c54c8c93, ADD INDEX IDX_31E581A0C54C8C93 (type_id)');
        $this->addSql('ALTER TABLE donation DROP INDEX idx_jc94819c83a9843, ADD INDEX IDX_31E581A0A9C80E3 (donateur_id)');
        $this->addSql('ALTER TABLE evenement CHANGE date_debut date_debut DATETIME DEFAULT NULL, CHANGE date_fin date_fin DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE forum CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE forum_reponse CHANGE date_reponse date_reponse DATETIME NOT NULL');
        $this->addSql('ALTER TABLE forum_reponse DROP INDEX idx_280a49b629ccbad0, ADD INDEX IDX_AE7A93B629CCBAD0 (forum_id)');
        $this->addSql('ALTER TABLE forum_reponse DROP INDEX idx_280a49b66c759d3, ADD INDEX IDX_AE7A93B660BB6FE6 (auteur_id)');
        $this->addSql('ALTER TABLE password_reset_token CHANGE expires_at expires_at DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE used_at used_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE password_reset_token DROP INDEX uniq_7268fa5e58af0efc, ADD UNIQUE INDEX UNIQ_6B7BA4B6B3BC57DA (token_hash)');
        $this->addSql('ALTER TABLE password_reset_token DROP INDEX idx_7268fa5ea76ed395, ADD INDEX IDX_6B7BA4B6A76ED395 (user_id)');
        $this->addSql('ALTER TABLE reservation CHANGE date_reservation date_reservation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user DROP IF EXISTS points, DROP IF EXISTS loyalty_level');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reset_token CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE used_at used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE password_reset_token DROP INDEX IDX_6B7BA4B6A76ED395, ADD INDEX IDX_7268FA5EA76ED395 (user_id)');
        $this->addSql('ALTER TABLE password_reset_token DROP INDEX UNIQ_6B7BA4B6B3BC57DA, ADD UNIQUE INDEX UNIQ_7268FA5E58AF0EFC (token_hash)');
        $this->addSql('ALTER TABLE `user` ADD points INT DEFAULT 0 NOT NULL, ADD loyalty_level VARCHAR(20) DEFAULT \'BRONZE\' NOT NULL');
        $this->addSql('ALTER TABLE donation CHANGE date_don date_don DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE donation DROP INDEX IDX_31E581A0A9C80E3, ADD INDEX IDX_JC94819C83A9843 (donateur_id)');
        $this->addSql('ALTER TABLE donation DROP INDEX IDX_31E581A0C54C8C93, ADD INDEX IDX_JC94819C54C8C93 (type_id)');
        $this->addSql('ALTER TABLE commande CHANGE date_commande date_commande DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE forum CHANGE date_creation date_creation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE evenement CHANGE date_debut date_debut DATETIME NOT NULL, CHANGE date_fin date_fin DATETIME NOT NULL');
        $this->addSql('ALTER TABLE forum_reponse CHANGE date_reponse date_reponse DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE forum_reponse DROP INDEX IDX_AE7A93B660BB6FE6, ADD INDEX IDX_280A49B66C759D3 (auteur_id)');
        $this->addSql('ALTER TABLE forum_reponse DROP INDEX IDX_AE7A93B629CCBAD0, ADD INDEX IDX_280A49B629CCBAD0 (forum_id)');
        $this->addSql('ALTER TABLE reservation CHANGE date_reservation date_reservation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
