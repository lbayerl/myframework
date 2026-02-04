<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260202140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mf_email_verification_token (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, consumed_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_DB97466EA76ED395 (user_id), INDEX idx_mf_evt_token (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mf_password_reset_token (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, consumed_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_49873D01A76ED395 (user_id), INDEX idx_mf_prt_token (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mf_push_subscription (id INT AUTO_INCREMENT NOT NULL, endpoint LONGTEXT NOT NULL, auth_token VARCHAR(255) NOT NULL, p256dh_key VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_9D46B54CA76ED395 (user_id), UNIQUE INDEX UNIQ_ENDPOINT (endpoint), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mf_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_verified TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX uniq_mf_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mf_email_verification_token ADD CONSTRAINT FK_DB97466EA76ED395 FOREIGN KEY (user_id) REFERENCES mf_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mf_password_reset_token ADD CONSTRAINT FK_49873D01A76ED395 FOREIGN KEY (user_id) REFERENCES mf_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mf_push_subscription ADD CONSTRAINT FK_9D46B54CA76ED395 FOREIGN KEY (user_id) REFERENCES mf_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mf_email_verification_token DROP FOREIGN KEY FK_DB97466EA76ED395');
        $this->addSql('ALTER TABLE mf_password_reset_token DROP FOREIGN KEY FK_49873D01A76ED395');
        $this->addSql('ALTER TABLE mf_push_subscription DROP FOREIGN KEY FK_9D46B54CA76ED395');
        $this->addSql('DROP TABLE mf_email_verification_token');
        $this->addSql('DROP TABLE mf_password_reset_token');
        $this->addSql('DROP TABLE mf_push_subscription');
        $this->addSql('DROP TABLE mf_user');
    }
}
