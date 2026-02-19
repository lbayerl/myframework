<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add artist enrichment fields to concert table (mbid, genres, wikipedia_url, artist_description)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE concert ADD mbid VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE concert ADD genres JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE concert ADD wikipedia_url VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE concert ADD artist_description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE concert DROP artist_description');
        $this->addSql('ALTER TABLE concert DROP wikipedia_url');
        $this->addSql('ALTER TABLE concert DROP genres');
        $this->addSql('ALTER TABLE concert DROP mbid');
    }
}
