<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add guest table and guest_owner/guest_purchaser columns to ticket table';
    }

    public function up(Schema $schema): void
    {
        // Create guest table
        $this->addSql('CREATE TABLE guest (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(180) DEFAULT NULL, created_by_id INT NOT NULL, converted_to_user_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL, INDEX idx_guest_name (name), INDEX IDX_ACB79A35B03A8386 (created_by_id), INDEX IDX_ACB79A35E2B371AB (converted_to_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE guest ADD CONSTRAINT FK_ACB79A35B03A8386 FOREIGN KEY (created_by_id) REFERENCES mf_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guest ADD CONSTRAINT FK_ACB79A35E2B371AB FOREIGN KEY (converted_to_user_id) REFERENCES mf_user (id) ON DELETE SET NULL');

        // Add guest columns to ticket table
        $this->addSql('ALTER TABLE ticket ADD guest_owner_id INT DEFAULT NULL, ADD guest_purchaser_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3A833006E FOREIGN KEY (guest_owner_id) REFERENCES guest (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3D8712B3A FOREIGN KEY (guest_purchaser_id) REFERENCES guest (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_97A0ADA3A833006E ON ticket (guest_owner_id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA3D8712B3A ON ticket (guest_purchaser_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove guest columns from ticket table
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3A833006E');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3D8712B3A');
        $this->addSql('DROP INDEX IDX_97A0ADA3A833006E ON ticket');
        $this->addSql('DROP INDEX IDX_97A0ADA3D8712B3A ON ticket');
        $this->addSql('ALTER TABLE ticket DROP guest_owner_id, DROP guest_purchaser_id');

        // Drop guest table
        $this->addSql('DROP TABLE guest');
    }
}
