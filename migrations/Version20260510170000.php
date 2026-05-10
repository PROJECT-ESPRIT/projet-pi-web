<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Symfony-only tables (forum, forum_reponse, ligne_commande, password_reset_token, messenger_messages) on the shared artconnect schema. JavaFX never queries these tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE IF NOT EXISTS password_reset_token (
            id INT AUTO_INCREMENT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            user_id INT NOT NULL,
            UNIQUE INDEX UNIQ_6B7BA4B6B3BC57DA (token_hash),
            INDEX IDX_6B7BA4B6A76ED395 (user_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_6B7BA4B6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");

        $this->addSql("CREATE TABLE IF NOT EXISTS forum (
            id INT AUTO_INCREMENT NOT NULL,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(180) NOT NULL,
            sujet VARCHAR(100) NOT NULL,
            message LONGTEXT NOT NULL,
            date_creation DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");

        $this->addSql("CREATE TABLE IF NOT EXISTS forum_reponse (
            id INT AUTO_INCREMENT NOT NULL,
            contenu LONGTEXT NOT NULL,
            date_reponse DATETIME NOT NULL,
            forum_id INT NOT NULL,
            auteur_id INT NOT NULL,
            INDEX IDX_AE7A93B629CCBAD0 (forum_id),
            INDEX IDX_AE7A93B660BB6FE6 (auteur_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_AE7A93B629CCBAD0 FOREIGN KEY (forum_id) REFERENCES forum (id) ON DELETE CASCADE,
            CONSTRAINT FK_AE7A93B660BB6FE6 FOREIGN KEY (auteur_id) REFERENCES `user` (id)
        ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");

        $this->addSql("CREATE TABLE IF NOT EXISTS ligne_commande (
            id INT AUTO_INCREMENT NOT NULL,
            quantite INT NOT NULL,
            prix_unitaire DOUBLE PRECISION NOT NULL,
            commande_id INT NOT NULL,
            produit_id INT NOT NULL,
            INDEX IDX_3170B74B82EA2E54 (commande_id),
            INDEX IDX_3170B74BF347EFB (produit_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_3170B74B82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE,
            CONSTRAINT FK_3170B74BF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)
        ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");

        $this->addSql("CREATE TABLE IF NOT EXISTS messenger_messages (
            id BIGINT AUTO_INCREMENT NOT NULL,
            body LONGTEXT NOT NULL,
            headers LONGTEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            available_at DATETIME NOT NULL,
            delivered_at DATETIME DEFAULT NULL,
            INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS forum_reponse');
        $this->addSql('DROP TABLE IF EXISTS forum');
        $this->addSql('DROP TABLE IF EXISTS ligne_commande');
        $this->addSql('DROP TABLE IF EXISTS password_reset_token');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }
}
