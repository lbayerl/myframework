<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add artist_image field to concert table for storing Wikipedia artist thumbnails.
 */
final class Version20260205120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add artist_image column to concert table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE concert ADD artist_image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE concert DROP artist_image');
    }
}
