<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Concert;
use App\Enum\ConcertStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Concert>
 */
final class ConcertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Concert::class);
    }

    /**
     * Findet alle veröffentlichten Konzerte, sortiert nach Datum (nächste zuerst).
     *
     * @return Concert[]
     */
    public function findUpcoming(int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.whenAt >= :now')
            ->setParameter('status', ConcertStatus::PUBLISHED)
            ->setParameter('now', new \DateTime('now'))
            ->orderBy('c.whenAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet vergangene Konzerte.
     *
     * @return Concert[]
     */
    public function findPast(int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.whenAt < :now')
            ->setParameter('status', ConcertStatus::PUBLISHED)
            ->setParameter('now', new \DateTime('now'))
            ->orderBy('c.whenAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alle Konzerte eines bestimmten Users (als Creator).
     *
     * @return Concert[]
     */
    public function findByCreator(int $userId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.createdBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.whenAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
