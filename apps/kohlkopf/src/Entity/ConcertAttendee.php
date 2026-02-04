<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttendeeStatus;
use App\Repository\ConcertAttendeeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use MyFramework\Core\Entity\User;

#[ORM\Entity(repositoryClass: ConcertAttendeeRepository::class)]
#[ORM\Table(name: 'concert_attendee')]
#[ORM\UniqueConstraint(name: 'uniq_concert_user', columns: ['concert_id', 'user_id'])]
final class ConcertAttendee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Concert::class, inversedBy: 'attendees')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Concert $concert = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: AttendeeStatus::class)]
    private AttendeeStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Concert $concert, User $user, AttendeeStatus $status = AttendeeStatus::INTERESTED)
    {
        $this->concert = $concert;
        $this->user = $user;
        $this->status = $status;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConcert(): ?Concert
    {
        return $this->concert;
    }

    public function setConcert(?Concert $concert): self
    {
        $this->concert = $concert;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getStatus(): AttendeeStatus
    {
        return $this->status;
    }

    public function setStatus(AttendeeStatus $status): self
    {
        $this->status = $status;
        $this->touch();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
