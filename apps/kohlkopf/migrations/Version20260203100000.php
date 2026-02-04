<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add created_by_id field to ticket table for tracking who created the ticket.
 */
final class Version20260203100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_by_id to ticket table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3B03A8386 FOREIGN KEY (created_by_id) REFERENCES mf_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_97A0ADA3B03A8386 ON ticket (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B03A8386');
        $this->addSql('DROP INDEX IDX_97A0ADA3B03A8386 ON ticket');
        $this->addSql('ALTER TABLE ticket DROP created_by_id');
    }
}
