<?php

namespace App\Repository;

use App\Entity\SubscriptionPayment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubscriptionPayment>
 */
class SubscriptionPaymentRepository extends ServiceEntityRepository
{
    private const MONTHS_RU = [
        1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
        5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
        9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPayment::class);
    }

    public function save(SubscriptionPayment $payment): void
    {
        $this->getEntityManager()->persist($payment);
        $this->getEntityManager()->flush();
    }

    public function getMonthlyStatsForUser(User $user): array
    {
        $payments = $this->createQueryBuilder('sp')
            ->innerJoin('sp.subscription', 's')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $stats = [];

        foreach ($payments as $payment) {
            $key = $payment->getPaidAt()->format('Y-m');

            if (!isset($stats[$key])) {
                $monthNum = (int) $payment->getPaidAt()->format('n');
                $year = $payment->getPaidAt()->format('Y');

                $stats[$key] = [
                    'label' => self::MONTHS_RU[$monthNum] . ' ' . $year,
                    'total' => 0.0,
                ];
            }

            $stats[$key]['total'] += $payment->getAmountInRub();
        }

        krsort($stats);

        return array_values($stats);
    }
}
