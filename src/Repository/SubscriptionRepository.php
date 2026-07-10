<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function save(Subscription $subscription): void
    {
        $this->getEntityManager()->persist($subscription);
        $this->getEntityManager()->flush();
    }

    public function remove(Subscription $subscription): void
    {
        $this->getEntityManager()->remove($subscription);
        $this->getEntityManager()->flush();
    }

    public function getById(int $id): ?Subscription
    {
        return $this->find($id);
    }

    public function findByExactName(string $name): ?Subscription
    {
        return $this->findOneBy(['name' => $name]);
    }
}
