<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GuestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use MyFramework\Core\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GuestRepository::class)]
#[ORM\Table(name: 'guest')]
#[ORM\Index(name: 'idx_guest_name', columns: ['name'])]
final class Guest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Assert\NotBlank(message: 'Name darf nicht leer sein.')]
    #[Assert\Length(min: 2, max: 120)]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    #[Assert\Email(message: 'Bitte gültige E-Mail angeben.')]
    private ?string $email = null;

    /** The app user who created this guest entry */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $createdBy = null;

    /** If this guest later registered, link to their user account */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $convertedToUser = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable('now');
        $this->createdAt = $now;
        $this->updatedAt = new \DateTime('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email !== null ? mb_strtolower($email) : null;
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

    public function getConvertedToUser(): ?User
    {
        return $this->convertedToUser;
    }

    public function setConvertedToUser(?User $user): self
    {
        $this->convertedToUser = $user;
        return $this;
    }

    public function isConverted(): bool
    {
        return $this->convertedToUser !== null;
    }

    public function getDisplayName(): string
    {
        return $this->name;
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
