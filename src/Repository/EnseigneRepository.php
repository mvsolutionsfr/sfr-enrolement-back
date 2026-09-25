<?php

namespace App\Repository;

use App\Entity\Enseigne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Enseigne>
 *
 * @method Enseigne|null find($id, $lockMode = null, $lockVersion = null)
 * @method Enseigne|null findOneBy(array $criteria, array $orderBy = null)
 * @method Enseigne[]    findAll()
 * @method Enseigne[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EnseigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enseigne::class);
    }

    public function save(Enseigne $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Enseigne $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Enseigne[] Returns an array of Client objects
     */
    public function findByNameByRS(string $critere): array
    {
        $critere = '%' . $critere . '%';
        return $this->createQueryBuilder('c')
            ->andWhere('c.raisonSociale LIKE :val')
            ->setParameter('val', $critere)
            ->orderBy('c.raisonSociale', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Enseigne[] Returns an array of Client objects
     */
    public function orderByName(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.raisonSociale', 'ASC')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Enseigne[] Returns an array of Enseigne objects
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

//    public function findOneBySomeField($value): ?Enseigne
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
