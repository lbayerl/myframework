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
     * Findet alle kommenden Konzerte, an denen der User teilnimmt, interessiert ist, oder ein Ticket hat.
     *
     * @return array<int, array{concert: Concert, userAttendance: string|null, hasTicket: bool, attendingCount: int, interestedCount: int}>
     */
    public function findUpcomingForUser(int $userId, int $limit = 50): array
    {
        // Get concerts where user is attending/interested
        $attendeeDql = "
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

        $attendeeResults = $this->getEntityManager()->createQuery($attendeeDql)
            ->setParameter('userId', $userId)
            ->setParameter('statuses', [AttendeeStatus::ATTENDING, AttendeeStatus::INTERESTED])
            ->setParameter('published', ConcertStatus::PUBLISHED)
            ->setParameter('now', new \DateTime('now'))
            ->setParameter('attending', AttendeeStatus::ATTENDING)
            ->setParameter('interested', AttendeeStatus::INTERESTED)
            ->setMaxResults($limit)
            ->getResult();

        $concertIds = [];
        $results = [];

        foreach ($attendeeResults as $row) {
            $concert = $row[0];
            $concertIds[$concert->getId()] = true;
            $attendance = $row['userAttendance'];
            $attendanceStr = $attendance instanceof AttendeeStatus ? $attendance->value : (string) $attendance;

            $results[] = [
                'concert' => $concert,
                'userAttendance' => $attendanceStr,
                'hasTicket' => ((int) $row['ticketCount']) > 0,
                'attendingCount' => (int) $row['attendingCount'],
                'interestedCount' => (int) $row['interestedCount'],
            ];
        }

        // Also get concerts where user has a ticket but no attendance record
        $ticketDql = "
            SELECT DISTINCT c,
                (SELECT COUNT(ca1.id) FROM App\Entity\ConcertAttendee ca1 WHERE ca1.concert = c AND ca1.status = :attending) AS attendingCount,
                (SELECT COUNT(ca2.id) FROM App\Entity\ConcertAttendee ca2 WHERE ca2.concert = c AND ca2.status = :interested) AS interestedCount
            FROM App\Entity\Concert c
            JOIN App\Entity\Ticket t WITH t.concert = c
            WHERE t.owner = :userId
                AND c.status = :published
                AND c.whenAt >= :now
            ORDER BY c.whenAt ASC
        ";

        $ticketResults = $this->getEntityManager()->createQuery($ticketDql)
            ->setParameter('userId', $userId)
            ->setParameter('published', ConcertStatus::PUBLISHED)
            ->setParameter('now', new \DateTime('now'))
            ->setParameter('attending', AttendeeStatus::ATTENDING)
            ->setParameter('interested', AttendeeStatus::INTERESTED)
            ->setMaxResults($limit)
            ->getResult();

        foreach ($ticketResults as $row) {
            $concert = $row[0];
            if (!isset($concertIds[$concert->getId()])) {
                $concertIds[$concert->getId()] = true;
                $results[] = [
                    'concert' => $concert,
                    'userAttendance' => null,
                    'hasTicket' => true,
                    'attendingCount' => (int) $row['attendingCount'],
                    'interestedCount' => (int) $row['interestedCount'],
                ];
            }
        }

        // Sort by date
        usort($results, fn($a, $b) => $a['concert']->getWhenAt() <=> $b['concert']->getWhenAt());

        return $results;
    }

    /**
     * Findet alle veröffentlichten Konzerte in einem Zeitfenster.
     *
     * @return Concert[]
     */
    public function findPublishedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.whenAt >= :from')
            ->andWhere('c.whenAt <= :to')
            ->setParameter('status', ConcertStatus::PUBLISHED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.whenAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
