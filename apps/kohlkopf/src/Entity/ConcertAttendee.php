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
#[ORM\UniqueConstraint(name: 'uniq_concert_guest', columns: ['concert_id', 'guest_id'])]
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
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Guest as attendee (alternative to user) */
    #[ORM\ManyToOne(targetEntity: Guest::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Guest $guest = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: AttendeeStatus::class)]
    private AttendeeStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Concert $concert, User|Guest $participant, AttendeeStatus $status = AttendeeStatus::INTERESTED)
    {
        $this->concert = $concert;
        if ($participant instanceof User) {
            $this->user = $participant;
        } else {
            $this->guest = $participant;
        }
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

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getGuest(): ?Guest
    {
        return $this->guest;
    }

    public function setGuest(?Guest $guest): self
    {
        $this->guest = $guest;
        return $this;
    }

    /**
     * Returns display name from user or guest.
     */
    public function getDisplayName(): string
    {
        if ($this->user !== null) {
            return $this->user->getDisplayName() ?? $this->user->getEmail();
        }
        if ($this->guest !== null) {
            return $this->guest->getDisplayName();
        }
        return 'Unbekannt';
    }

    /**
     * Whether this attendee is a guest (vs. registered user).
     */
    public function isGuest(): bool
    {
        return $this->guest !== null;
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
