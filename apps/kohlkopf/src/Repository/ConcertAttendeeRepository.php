<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Concert;
use App\Entity\ConcertAttendee;
use App\Entity\Guest;
use App\Enum\AttendeeStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use MyFramework\Core\Entity\User;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<ConcertAttendee>
 */
final class ConcertAttendeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConcertAttendee::class);
    }

    /**
     * Findet alle Attendees für ein bestimmtes Konzert, sortiert nach Status.
     *
     * @return ConcertAttendee[]
     */
    public function findByConcertSorted(Concert $concert): array
    {
        return $this->createQueryBuilder('ca')
            ->where('ca.concert = :concertId')
            ->setParameter('concertId', $concert->getId(), UuidType::NAME)
            ->addSelect("CASE
                WHEN ca.status = 'ATTENDING' THEN 1
                WHEN ca.status = 'INTERESTED' THEN 2
                WHEN ca.status = 'PARTICIPATED' THEN 3
                ELSE 4
            END AS HIDDEN sortOrder")
            ->orderBy('sortOrder', 'ASC')
            ->addOrderBy('ca.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet den Attendee-Status eines Users für ein Konzert.
     */
    public function findOneByUserAndConcert(User $user, Concert $concert): ?ConcertAttendee
    {
        return $this->createQueryBuilder('ca')
            ->where('ca.user = :user')
            ->andWhere('ca.concert = :concertId')
            ->setParameter('user', $user)
            ->setParameter('concertId', $concert->getId(), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Findet alle Attendees mit bestimmtem Status für ein Konzert.
     *
     * @return ConcertAttendee[]
     */
    public function findByConcertAndStatus(Concert $concert, AttendeeStatus $status): array
    {
        return $this->createQueryBuilder('ca')
            ->where('ca.concert = :concertId')
            ->andWhere('ca.status = :status')
            ->setParameter('concertId', $concert->getId(), UuidType::NAME)
            ->setParameter('status', $status)
            ->orderBy('ca.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alle User mit Status ATTENDING oder INTERESTED für ein Konzert.
     *
     * @return ConcertAttendee[]
     */
    public function findActiveAttendees(Concert $concert): array
    {
        return $this->createQueryBuilder('ca')
            ->where('ca.concert = :concertId')
            ->andWhere('ca.status IN (:statuses)')
            ->setParameter('concertId', $concert->getId(), UuidType::NAME)
            ->setParameter('statuses', [AttendeeStatus::ATTENDING, AttendeeStatus::INTERESTED])
            ->orderBy('ca.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Zählt alle Attendees mit einem bestimmten Status.
     */
    public function countByStatus(Concert $concert, AttendeeStatus $status): int
    {
        return (int) $this->createQueryBuilder('ca')
            ->select('COUNT(ca.id)')
            ->where('ca.concert = :concertId')
            ->andWhere('ca.status = :status')
            ->setParameter('concertId', $concert->getId(), UuidType::NAME)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Findet alle Attendees für ein bestimmtes Konzert.
     *
     * @return ConcertAttendee[]
     */
    public function findByConcert(string $concertId): array
    {
        return $this->createQueryBuilder('ca')
            ->where('ca.concert = :concertId')
            ->setParameter('concertId', $concertId)
            ->orderBy('ca.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet alle Konzerte, an denen ein User teilnimmt.
     *
     * @return ConcertAttendee[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('ca')
            ->where('ca.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('ca.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet den Attendee-Status eines Gasts für ein Konzert.
     */
    public function findOneByGuestAndConcert(Guest $guest, Concert $concert): ?ConcertAttendee
    {
        return $this->createQueryBuilder('ca')
            ->where('ca.guest = :guest')
            ->andWhere('ca.concert = :concertId')
            ->setParameter('guest', $guest)
            ->setParameter('concertId', $concert->getId(), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
