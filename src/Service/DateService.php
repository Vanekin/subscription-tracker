<?php

namespace App\Service;

use App\Enum\BillingPeriodType;

class DateService
{
    public function calculateNextBillingTime(
        \DateTime $date,
        BillingPeriodType $billingPeriod,
        ?int $customValue = null,
        ?int $preferredDay = null
    ): \DateTime
    {
        // Работаем с копией, чтобы не мутировать исходный объект даты
        // (иначе startDate подписки незаметно изменился бы вместе с nextPaymentDate,
        // т.к. \DateTime - мутабельный объект и modify()/setDate() меняют его "на месте")
        $date = clone $date;

        $day = $preferredDay ?? (int) $date->format('j');

        switch($billingPeriod) {
            case BillingPeriodType::DAILY:
                $date->modify('+1 day');
                break;
            case BillingPeriodType::WEEKLY:
                $date->modify('+ 1 week');
                break;
            case BillingPeriodType::MONTHLY:
                $date->modify('+ 1 month');
                $date->setDate(
                    (int) $date->format('Y'),
                    (int) $date->format('m'),
                    min($day, (int) $date->format('t')),
                );
                break;
            case BillingPeriodType::QUARTERLY:
                $date->modify('+ 3 months');
                $date->setDate(
                    (int) $date->format('Y'),
                    (int) $date->format('m'),
                    min($day, (int) $date->format('t')),
                );
                break;
            case BillingPeriodType::SEMIANNUALLY:
                $date->modify('+ 6 month');
                $date->setDate(
                    (int) $date->format('Y'),
                    (int) $date->format('m'),
                    min($day, (int) $date->format('t')),
                );
                break;
            case BillingPeriodType::YEARLY:
                $date->modify('+ 1 year');
                $date->setDate(
                    (int) $date->format('Y'),
                    (int) $date->format('m'),
                    min($day, (int) $date->format('t')),
                );
                break;
            case BillingPeriodType::CUSTOM:
                $date->modify(sprintf('+%d days', $customValue ?? 30));
                break;
        }
        return $date;
    }
}
