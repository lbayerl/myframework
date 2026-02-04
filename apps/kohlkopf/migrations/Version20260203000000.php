<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Add Concert, Ticket and Payment tables
 */
final class Version20260203000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Concert, ConcertAttendee, Ticket and Payment tables for Kohlkopf app';
    }

    public function up(Schema $schema): void
    {
        // Concert table
        $this->addSql('CREATE TABLE concert (id BINARY(16) NOT NULL, title VARCHAR(120) NOT NULL, when_at DATETIME NOT NULL, where_text VARCHAR(200) NOT NULL, comment LONGTEXT DEFAULT NULL, external_link VARCHAR(255) DEFAULT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, cancelled_at DATETIME DEFAULT NULL, created_by_id INT NOT NULL, INDEX IDX_D57C02D2B03A8386 (created_by_id), INDEX idx_concert_when (when_at), INDEX idx_concert_status_when (status, when_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        
        // Concert Attendee table
        $this->addSql('CREATE TABLE concert_attendee (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, concert_id BINARY(16) NOT NULL, user_id INT NOT NULL, INDEX IDX_2E7FC90683C97B2E (concert_id), INDEX IDX_2E7FC906A76ED395 (user_id), UNIQUE INDEX uniq_concert_user (concert_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        
        // Payment table
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(10, 2) NOT NULL, paid_at DATETIME NOT NULL, note VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, ticket_id BINARY(16) DEFAULT NULL, from_user_id INT NOT NULL, to_user_id INT NOT NULL, INDEX IDX_6D28840D700047D2 (ticket_id), INDEX IDX_6D28840D2130303A (from_user_id), INDEX IDX_6D28840D29F6EE60 (to_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        
        // Ticket table
        $this->addSql('CREATE TABLE ticket (id BINARY(16) NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, is_paid TINYINT DEFAULT 0 NOT NULL, purchaser_paid_at DATETIME DEFAULT NULL, external_id VARCHAR(255) DEFAULT NULL, seat VARCHAR(64) DEFAULT NULL, type VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, concert_id BINARY(16) NOT NULL, owner_id INT DEFAULT NULL, purchaser_id INT DEFAULT NULL, INDEX IDX_97A0ADA383C97B2E (concert_id), INDEX IDX_97A0ADA37E3C61F9 (owner_id), INDEX IDX_97A0ADA3ED255ED6 (purchaser_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        
        // Foreign keys
        $this->addSql('ALTER TABLE concert ADD CONSTRAINT FK_D57C02D2B03A8386 FOREIGN KEY (created_by_id) REFERENCES mf_user (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE concert_attendee ADD CONSTRAINT FK_2E7FC90683C97B2E FOREIGN KEY (concert_id) REFERENCES concert (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE concert_attendee ADD CONSTRAINT FK_2E7FC906A76ED395 FOREIGN KEY (user_id) REFERENCES mf_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D2130303A FOREIGN KEY (from_user_id) REFERENCES mf_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D29F6EE60 FOREIGN KEY (to_user_id) REFERENCES mf_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA383C97B2E FOREIGN KEY (concert_id) REFERENCES concert (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA37E3C61F9 FOREIGN KEY (owner_id) REFERENCES mf_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3ED255ED6 FOREIGN KEY (purchaser_id) REFERENCES mf_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys first
        $this->addSql('ALTER TABLE concert DROP FOREIGN KEY FK_D57C02D2B03A8386');
        $this->addSql('ALTER TABLE concert_attendee DROP FOREIGN KEY FK_2E7FC90683C97B2E');
        $this->addSql('ALTER TABLE concert_attendee DROP FOREIGN KEY FK_2E7FC906A76ED395');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D700047D2');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D2130303A');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D29F6EE60');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA383C97B2E');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA37E3C61F9');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3ED255ED6');
        
        // Drop tables
        $this->addSql('DROP TABLE concert');
        $this->addSql('DROP TABLE concert_attendee');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE ticket');
    }
}
