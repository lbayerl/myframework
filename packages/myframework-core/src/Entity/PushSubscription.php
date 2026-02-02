<?php

declare(strict_types=1);

namespace MyFramework\Core\Entity;

use Doctrine\ORM\Mapping as ORM;
use MyFramework\Core\Push\Repository\PushSubscriptionRepository;
use MyFramework\Core\Entity\User;

#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\Table(name: 'mf_push_subscription')]
#[ORM\UniqueConstraint(name: 'UNIQ_ENDPOINT', columns: ['endpoint'])]
final class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'text')]
    private string $endpoint;

    #[ORM\Column(type: 'string', length: 255)]
    private string $authToken;

    #[ORM\Column(type: 'string', length: 255)]
    private string $p256dhKey;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        string $endpoint,
        string $authToken,
        string $p256dhKey,
    ) {
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->authToken = $authToken;
        $this->p256dhKey = $p256dhKey;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getP256dhKey(): string
    {
        return $this->p256dhKey;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
