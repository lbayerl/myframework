<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Guest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use MyFramework\Core\Entity\User;

/**
 * @extends ServiceEntityRepository<Guest>
 */
final class GuestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Guest::class);
    }

    /**
     * Search guests by name (for autocomplete/dropdown).
     *
     * @return Guest[]
     */
    public function searchByName(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.name LIKE :query')
            ->andWhere('g.convertedToUser IS NULL')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('g.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all non-converted guests, sorted by name.
     *
     * @return Guest[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.convertedToUser IS NULL')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all guests (including converted), sorted by name.
     *
     * @return Guest[]
     */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find guest by email (for conversion matching).
     */
    public function findByEmail(string $email): ?Guest
    {
        return $this->createQueryBuilder('g')
            ->where('g.email = :email')
            ->andWhere('g.convertedToUser IS NULL')
            ->setParameter('email', mb_strtolower($email))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all unconverted guests matching an email.
     *
     * @return Guest[]
     */
    public function findAllByEmail(string $email): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.email = :email')
            ->andWhere('g.convertedToUser IS NULL')
            ->setParameter('email', mb_strtolower($email))
            ->getQuery()
            ->getResult();
    }

    /**
     * Find guests created by a specific user.
     *
     * @return Guest[]
     */
    public function findByCreator(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
