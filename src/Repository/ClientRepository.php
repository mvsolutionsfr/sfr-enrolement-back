<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 *
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function save(Client $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Client $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Client[] Returns an array of Client objects
     */
    public function findByNameByEnseigne($raisonSociale,$enseigne): array
    {
        $critere = '%' . $raisonSociale . '%';
        return $this->createQueryBuilder('c')
            ->andWhere('c.raisonSociale LIKE :val')
            ->andWhere('c.enseigne = :enseigne')
            ->setParameter('val', $critere)
            ->setParameter('enseigne', $enseigne)
            ->orderBy('c.raisonSociale', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }


        /**
     * @return Client[] Returns an array of Client objects
     */
    public function getLastClientsByUtilisateur(Utilisateur $utilisateur): array
    {
        $result=$this->createQueryBuilder('c')
            ->andWhere('c.utilisateur = :val')
            ->setParameter('val', $utilisateur)
            ->orderBy('c.id','DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        return $result;
    }

}
