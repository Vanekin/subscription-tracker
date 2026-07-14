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

    public function findDueForReminder(\DateTimeInterface $targetDate): array
    {
        $start = \DateTime::createFromInterface($targetDate);
        $start->setTime(0, 0, 0);

        $end = clone $start;
        $end->setTime(23, 59, 59);

        return $this->createQueryBuilder('s')
            ->andWhere('s.nextPaymentDate BETWEEN :start AND :end')
            ->andWhere('s.user IS NOT NULL')
            ->andWhere('s.cancelledAt IS NULL')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }
}
