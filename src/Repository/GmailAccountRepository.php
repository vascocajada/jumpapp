<?php

namespace App\Repository;

use App\Entity\GmailAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GmailAccount>
 *
 * @method GmailAccount|null find($id, $lockMode = null, $lockVersion = null)
 * @method GmailAccount|null findOneBy(array $criteria, array $orderBy = null)
 * @method GmailAccount[]    findAll()
 * @method GmailAccount[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GmailAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GmailAccount::class);
    }

    public function save(GmailAccount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GmailAccount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
} 