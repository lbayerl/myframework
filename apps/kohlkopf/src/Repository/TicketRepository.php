<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Concert;
use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use MyFramework\Core\Entity\User;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
final class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * Findet alle Tickets für ein bestimmtes Konzert mit User-Joins.
     *
     * @return Ticket[]
     */
    public function findByConcertWithUsers(Concert $concert): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.owner', 'o')
            ->leftJoin('t.purchaser', 'p')
            ->addSelect('o', 'p')
            ->where('t.concert = :concert')
            ->setParameter('concert', $concert->getId(), UuidType::NAME)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alle unbezahlten Tickets für ein Konzert, bei denen Owner != Purchaser.
     *
     * @return Ticket[]
     */
    public function findUnpaidWithDebt(Concert $concert): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.owner', 'o')
            ->leftJoin('t.purchaser', 'p')
            ->addSelect('o', 'p')
            ->where('t.concert = :concert')
            ->andWhere('t.isPaid = false')
            ->andWhere('t.owner IS NOT NULL')
            ->andWhere('t.purchaser IS NOT NULL')
            ->andWhere('t.owner != t.purchaser')
            ->setParameter('concert', $concert->getId(), UuidType::NAME)
            ->getQuery()
            ->getResult();
    }

    /**
     * Zählt Tickets für ein Konzert.
     */
    public function countByConcert(Concert $concert): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.concert = :concert')
            ->setParameter('concert', $concert->getId(), UuidType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Prüft, ob ein User bereits ein Ticket für dieses Konzert hat.
     */
    public function hasTicketForConcert(User $user, Concert $concert): bool
    {
        $count = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.concert = :concert')
            ->andWhere('t.owner = :user')
            ->setParameter('concert', $concert->getId(), UuidType::NAME)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
        
        return $count > 0;
    }

    /**
     * Findet alle Tickets für ein bestimmtes Konzert.
     *
     * @return Ticket[]
     */
    public function findByConcert(string $concertId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.concert = :concertId')
            ->setParameter('concertId', $concertId, UuidType::NAME)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alle Tickets eines Users (als Owner).
     *
     * @return Ticket[]
     */
    public function findByOwner(int $userId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.owner = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alle Tickets, die ein User gekauft hat.
     *
     * @return Ticket[]
     */
    public function findByPurchaser(int $userId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.purchaser = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
