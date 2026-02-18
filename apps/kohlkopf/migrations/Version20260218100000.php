<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add guest_id column to concert_attendee table for guest attendance support';
    }

    public function up(Schema $schema): void
    {
        // Make user_id nullable (guests don't have a user)
        $this->addSql('ALTER TABLE concert_attendee MODIFY user_id INT DEFAULT NULL');

        // Add guest_id column
        $this->addSql('ALTER TABLE concert_attendee ADD guest_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE concert_attendee ADD CONSTRAINT FK_408B70629A4AA658 FOREIGN KEY (guest_id) REFERENCES guest (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_408B70629A4AA658 ON concert_attendee (guest_id)');

        // Add unique constraint for concert + guest
        $this->addSql('CREATE UNIQUE INDEX uniq_concert_guest ON concert_attendee (concert_id, guest_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove guest support
        $this->addSql('DROP INDEX uniq_concert_guest ON concert_attendee');
        $this->addSql('ALTER TABLE concert_attendee DROP FOREIGN KEY FK_408B70629A4AA658');
        $this->addSql('DROP INDEX IDX_408B70629A4AA658 ON concert_attendee');
        $this->addSql('ALTER TABLE concert_attendee DROP guest_id');

        // Make user_id NOT NULL again
        $this->addSql('ALTER TABLE concert_attendee MODIFY user_id INT NOT NULL');
    }
}
