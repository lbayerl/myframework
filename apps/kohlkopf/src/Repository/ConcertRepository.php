<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Concert;
use App\Enum\AttendeeStatus;
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
     * Findet alle veröffentlichten Konzerte mit Teilnehmerzahlen.
     *
     * @return array<int, array{concert: Concert, attendingCount: int, interestedCount: int}>
     */
    public function findUpcomingWithCounts(int $limit = 50): array
    {
        $dql = "
            SELECT c,
                (SELECT COUNT(ca1.id) FROM App\Entity\ConcertAttendee ca1 WHERE ca1.concert = c AND ca1.status = :attending) AS attendingCount,
                (SELECT COUNT(ca2.id) FROM App\Entity\ConcertAttendee ca2 WHERE ca2.concert = c AND ca2.status = :interested) AS interestedCount
            FROM App\Entity\Concert c
            WHERE c.status = :status AND c.whenAt >= :now
            ORDER BY c.whenAt ASC
        ";

        $results = $this->getEntityManager()->createQuery($dql)
            ->setParameter('status', ConcertStatus::PUBLISHED)
            ->setParameter('now', new \DateTime('now'))
            ->setParameter('attending', AttendeeStatus::ATTENDING)
            ->setParameter('interested', AttendeeStatus::INTERESTED)
            ->setMaxResults($limit)
            ->getResult();

        return array_map(fn($row) => [
            'concert' => $row[0],
            'attendingCount' => (int) $row['attendingCount'],
            'interestedCount' => (int) $row['interestedCount'],
        ], $results);
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
     * Findet vergangene Konzerte mit Teilnehmerzahlen.
     *
     * @return array<int, array{concert: Concert, attendingCount: int, interestedCount: int}>
     */
    public function findPast(int $limit = 50): array
    {
        $dql = "
            SELECT c,
                (SELECT COUNT(ca1.id) FROM App\Entity\ConcertAttendee ca1 WHERE ca1.concert = c AND ca1.status = :attending) AS attendingCount,
                (SELECT COUNT(ca2.id) FROM App\Entity\ConcertAttendee ca2 WHERE ca2.concert = c AND ca2.status = :interested) AS interestedCount
            FROM App\Entity\Concert c
            WHERE c.status = :status AND c.whenAt < :now
            ORDER BY c.whenAt DESC
        ";

        $results = $this->getEntityManager()->createQuery($dql)
            ->setParameter('status', ConcertStatus::PUBLISHED)
            ->setParameter('now', new \DateTime('now'))
            ->setParameter('attending', AttendeeStatus::ATTENDING)
            ->setParameter('interested', AttendeeStatus::INTERESTED)
            ->setMaxResults($limit)
            ->getResult();

        return array_map(fn($row) => [
            'concert' => $row[0],
            'attendingCount' => (int) $row['attendingCount'],
            'interestedCount' => (int) $row['interestedCount'],
        ], $results);
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

    /**
     * Findet alle Konzerte (inklusive abgesagte und Entwürfe) mit Teilnehmerzahlen.
     *
     * @return array<int, array{concert: Concert, attendingCount: int, interestedCount: int}>
     */
    public function findAll(int $limit = 100): array
    {
        $dql = "
            SELECT c,
                (SELECT COUNT(ca1.id) FROM App\Entity\ConcertAttendee ca1 WHERE ca1.concert = c AND ca1.status = :attending) AS attendingCount,
                (SELECT COUNT(ca2.id) FROM App\Entity\ConcertAttendee ca2 WHERE ca2.concert = c AND ca2.status = :interested) AS interestedCount
            FROM App\Entity\Concert c
            ORDER BY c.whenAt DESC
        ";

        $results = $this->getEntityManager()->createQuery($dql)
            ->setParameter('attending', AttendeeStatus::ATTENDING)
            ->setParameter('interested', AttendeeStatus::INTERESTED)
            ->setMaxResults($limit)
            ->getResult();

        return array_map(fn($row) => [
            'concert' => $row[0],
            'attendingCount' => (int) $row['attendingCount'],
            'interestedCount' => (int) $row['interestedCount'],
        ], $results);
    }

    /**
     * Findet alle kommenden Konzerte, an denen der User teilnimmt oder interessiert ist.
     *
     * @return array<int, array{concert: Concert, userAttendance: string, hasTicket: bool, attendingCount: int, interestedCount: int}>
     */
    public function findUpcomingForUser(int $userId, int $limit = 50): array
    {
        $dql = "
            SELECT c, ca.status AS userAttendance,
                (SELECT COUNT(ca1.id) FROM App\Entity\ConcertAttendee ca1 WHERE ca1.concert = c AND ca1.status = :attending) AS attendingCount,
                (SELECT COUNT(ca2.id) FROM App\Entity\ConcertAttendee ca2 WHERE ca2.concert = c AND ca2.status = :interested) AS interestedCount,
                (SELECT COUNT(t.id) FROM App\Entity\Ticket t WHERE t.concert = c AND t.owner = :userId) AS ticketCount
            FROM App\Entity\Concert c
            JOIN c.attendees ca
            WHERE ca.user = :userId
                AND ca.status IN (:statuses)
                AND c.status = :published
                AND c.whenAt >= :now
            ORDER BY c.whenAt ASC
        ";

        $results = $this->getEntityManager()->createQuery($dql)
            ->setParameter('userId', $userId)
            ->setParameter('statuses', [AttendeeStatus::ATTENDING, AttendeeStatus::INTERESTED])
            ->setParameter('published', ConcertStatus::PUBLISHED)
            ->setParameter('now', new \DateTime('now'))
            ->setParameter('attending', AttendeeStatus::ATTENDING)
            ->setParameter('interested', AttendeeStatus::INTERESTED)
            ->setMaxResults($limit)
            ->getResult();

        return array_map(function ($row) {
            $attendance = $row['userAttendance'];
            $attendanceStr = $attendance instanceof AttendeeStatus ? $attendance->value : (string) $attendance;
            
            return [
                'concert' => $row[0],
                'userAttendance' => $attendanceStr,
                'hasTicket' => ((int) $row['ticketCount']) > 0,
                'attendingCount' => (int) $row['attendingCount'],
                'interestedCount' => (int) $row['interestedCount'],
            ];
        }, $results);
    }
}
