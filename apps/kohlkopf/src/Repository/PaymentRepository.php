<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
final class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Findet alle Zahlungen von einem User.
     *
     * @return Payment[]
     */
    public function findByFromUser(int $userId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.fromUser = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('p.paidAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alle Zahlungen an einen User.
     *
     * @return Payment[]
     */
    public function findByToUser(int $userId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.toUser = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('p.paidAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
