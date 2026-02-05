<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ConcertStatus;
use App\Repository\ConcertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use MyFramework\Core\Entity\User;

#[ORM\Entity(repositoryClass: ConcertRepository::class)]
#[ORM\Table(name: 'concert')]
#[ORM\Index(name: 'idx_concert_when', columns: ['when_at'])]
#[ORM\Index(name: 'idx_concert_status_when', columns: ['status', 'when_at'])]
final class Concert
{
    #[ORM\Id]
    #[ORM\GeneratedValue('CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    /** UI: „Wer?" (Künstler/Band) */
    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Assert\NotBlank(message: 'Titel darf nicht leer sein.')]
    #[Assert\Length(min: 2, max: 120)]
    private string $title = '';

    /** UI: „Wann?" (Europe/Berlin anzeigen; DB in UTC ok) */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'Datum/Zeit wird benötigt.')]
    private \DateTime $whenAt;

    /** UI: „Wo?" (frei; z. B. Venue, Stadt) */
    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank(message: 'Ort darf nicht leer sein.')]
    #[Assert\Length(min: 2, max: 200)]
    private string $whereText = '';

    /** UI: „Kommentar" (optional) */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $comment = null;

    /** UI: „Link" (optional; Ticketshop/Webseite) */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Url(protocols: ['http', 'https'], message: 'Bitte gültige URL angeben.')]
    private ?string $externalLink = null;

    /** Creator = darf bearbeiten/löschen (plus Admin) */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: ConcertStatus::class)]
    private ConcertStatus $status = ConcertStatus::PUBLISHED;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $updatedAt;

    /** gesetzt, wenn abgesagt */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $cancelledAt = null;

    /** Lokaler Pfad zum Künstlerbild (z.B. /images/artists/band-xyz.jpg) */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $artistImage = null;

    #[ORM\OneToMany(targetEntity: ConcertAttendee::class, mappedBy: 'concert', cascade: ['persist', 'remove'])]
    private Collection $attendees;

    public function __construct()
    {
        $now = new \DateTimeImmutable('now');
        $this->createdAt = $now;
        $this->updatedAt = new \DateTime('now');
        // default future placeholder to avoid nulls; typically set via form
        $this->whenAt = (new \DateTime('now'))->modify('+1 day');
        $this->attendees = new ArrayCollection();
    }

    // ===== lifecycle helpers =====
    public function touch(): void
    {
        $this->updatedAt = new \DateTime('now');
    }

    public function cancel(): void
    {
        $this->status = ConcertStatus::CANCELLED;
        $this->cancelledAt = new \DateTime('now');
        $this->touch();
    }

    // ===== getters/setters =====
    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getWhenAt(): \DateTime
    {
        return $this->whenAt;
    }

    public function setWhenAt(\DateTime $when): self
    {
        $this->whenAt = $when;
        return $this;
    }

    public function getWhereText(): string
    {
        return $this->whereText;
    }

    public function setWhereText(string $where): self
    {
        $this->whereText = $where;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getExternalLink(): ?string
    {
        return $this->externalLink;
    }

    public function setExternalLink(?string $link): self
    {
        $this->externalLink = $link;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $user): self
    {
        $this->createdBy = $user;
        return $this;
    }

    public function getStatus(): ConcertStatus
    {
        return $this->status;
    }

    public function setStatus(ConcertStatus $status): self
    {
        $this->status = $status;
        // Wenn Status != CANCELLED, dann cancelledAt nullen
        if ($status !== ConcertStatus::CANCELLED) {
            $this->cancelledAt = null;
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function getCancelledAt(): ?\DateTime
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTime $dt): self
    {
        $this->cancelledAt = $dt;
        return $this;
    }

    public function getArtistImage(): ?string
    {
        return $this->artistImage;
    }

    public function setArtistImage(?string $path): self
    {
        $this->artistImage = $path;
        return $this;
    }

    /**
     * @return Collection<int, ConcertAttendee>
     */
    public function getAttendees(): Collection
    {
        return $this->attendees;
    }

    public function addAttendee(ConcertAttendee $attendee): self
    {
        if (!$this->attendees->contains($attendee)) {
            $this->attendees->add($attendee);
            $attendee->setConcert($this);
        }
        return $this;
    }

    public function removeAttendee(ConcertAttendee $attendee): self
    {
        if ($this->attendees->removeElement($attendee)) {
            if ($attendee->getConcert() === $this) {
                $attendee->setConcert(null);
            }
        }
        return $this;
    }
}
