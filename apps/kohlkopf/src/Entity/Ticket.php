<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TicketType;
use App\Repository\TicketRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use MyFramework\Core\Entity\User;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: 'ticket')]
final class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue('CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Concert::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Concert $concert = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $purchaser = null;

    /** Who created this ticket entry (for edit/delete permissions) */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $price = null; // stored as string by Doctrine for decimals

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPaid = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $purchaserPaidAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $seat = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: TicketType::class)]
    private TicketType $type = TicketType::APP_TICKET;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $updatedAt;

    public function __construct(Concert $concert, ?User $purchaser = null, TicketType $type = TicketType::APP_TICKET)
    {
        $now = new \DateTimeImmutable('now');
        $this->createdAt = $now;
        $this->updatedAt = new \DateTime('now');
        $this->concert = $concert;
        $this->purchaser = $purchaser;
        $this->type = $type;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getConcert(): ?Concert
    {
        return $this->concert;
    }

    public function setConcert(Concert $concert): self
    {
        $this->concert = $concert;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getPurchaser(): ?User
    {
        return $this->purchaser;
    }

    public function setPurchaser(?User $purchaser): self
    {
        $this->purchaser = $purchaser;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function isPaid(): bool
    {
        return $this->isPaid;
    }

    public function setIsPaid(bool $isPaid): self
    {
        $this->isPaid = $isPaid;
        if ($isPaid && $this->purchaserPaidAt === null) {
            $this->purchaserPaidAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getPurchaserPaidAt(): ?\DateTimeImmutable
    {
        return $this->purchaserPaidAt;
    }

    public function setPurchaserPaidAt(?\DateTimeImmutable $purchaserPaidAt): self
    {
        $this->purchaserPaidAt = $purchaserPaidAt;
        if ($purchaserPaidAt !== null) {
            $this->isPaid = true;
        }
        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getSeat(): ?string
    {
        return $this->seat;
    }

    public function setSeat(?string $seat): self
    {
        $this->seat = $seat;
        return $this;
    }

    public function getType(): TicketType
    {
        return $this->type;
    }

    public function setType(TicketType $type): self
    {
        $this->type = $type;
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

    public function touch(): void
    {
        $this->updatedAt = new \DateTime('now');
    }
}
