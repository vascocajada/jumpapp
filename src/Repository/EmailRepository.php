<?php

namespace App\Repository;

use App\Entity\Email;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Email>
 */
class EmailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Email::class);
    }

    /**
     * Find emails for a category with pagination
     */
    public function findByCategoryWithPagination($category, $user, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.category = :category')
            ->andWhere('e.owner = :user')
            ->setParameter('category', $category)
            ->setParameter('user', $user)
            ->orderBy('e.receivedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
            
        return $qb->getQuery()->getResult();
    }

    /**
     * Count total emails for a category
     */
    public function countByCategory($category, $user): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.category = :category')
            ->andWhere('e.owner = :user')
            ->setParameter('category', $category)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

//    /**
//     * @return Email[] Returns an array of Email objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Email
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
