<?php

declare(strict_types=1);

namespace MyFramework\Core\Push\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use MyFramework\Core\Entity\PushSubscription;
use MyFramework\Core\Entity\User;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
final class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    public function save(PushSubscription $subscription): void
    {
        $this->getEntityManager()->persist($subscription);
        $this->getEntityManager()->flush();
    }

    public function remove(PushSubscription $subscription): void
    {
        $this->getEntityManager()->remove($subscription);
        $this->getEntityManager()->flush();
    }

    public function findByEndpoint(string $endpoint): ?PushSubscription
    {
        return $this->findOneBy(['endpoint' => $endpoint]);
    }

    /**
     * @return PushSubscription[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    /**
     * @return PushSubscription[]
     */
    public function findAll(): array
    {
        return parent::findAll();
    }
}
