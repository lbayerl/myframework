<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional device_label to mf_push_subscription';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mf_push_subscription ADD device_label VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mf_push_subscription DROP device_label');
    }
}
