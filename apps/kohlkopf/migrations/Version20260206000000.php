<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add displayName column to mf_user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mf_user ADD display_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mf_user DROP display_name');
    }
}
