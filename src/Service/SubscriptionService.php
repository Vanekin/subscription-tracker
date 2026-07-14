<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\SubscriptionPayment;
use App\Enum\BillingPeriodType;
use App\Form\SubscriptionFormType;
use App\Repository\SubscriptionRepository;
use App\Entity\User;
use Symfony\Component\Form\FormInterface;

class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly DateService $dateService,
        private readonly CurrencyConversionService $currencyConversionService,
    )
    {

    }

    public function saveNewSubscription(Subscription $subscription, User $user): Subscription
    {
        $subscription->setUser($user);

        if (!$subscription->getStartDate()) {
            $subscription->setStartDate(new \DateTime());
        }

        $billingPeriod = BillingPeriodType::from($subscription->getBillingPeriod());

        if ($billingPeriod === BillingPeriodType::CUSTOM && !$subscription->getCustomPeriodDays()) {
            throw new \InvalidArgumentException('Для своего периода оплаты укажите количество дней');
        }

        $nextPaymentDate = $this->dateService->calculateNextBillingTime(
            $subscription->getStartDate(),
            $billingPeriod,
            $subscription->getCustomPeriodDays(),
            $subscription->getPreferredDay() ?? (int) $subscription->getStartDate()->format('j')
        );

        $subscription->setNextPaymentDate($nextPaymentDate);

        $initialPayment = $this->createPaymentRecord($subscription, $subscription->getStartDate());
        $subscription->addPayment($initialPayment);

        $this->subscriptionRepository->save($subscription);

        return $subscription;
    }


    public function getUserSubscription(int $id, ?User $user): ?Subscription
    {
        if (!$user) {
            return null;
        }

        $subscription = $this->subscriptionRepository->getById($id);

        if (!$subscription || $subscription->getUser()?->getId() !== $user->getId()) {
            return null;
        }

        return $subscription;
    }

    public function markAsPaid(Subscription $subscription): SubscriptionPayment
    {
        if (!$subscription->isActive()) {
            throw new \InvalidArgumentException('Подписка завершена - отметить оплату нельзя. Сначала возобновите подписку.');
        }

        $payment = $this->createPaymentRecord($subscription, new \DateTime());

        $billingPeriod = BillingPeriodType::from($subscription->getBillingPeriod());

        $baseDate = $subscription->getNextPaymentDate() ?? new \DateTime();

        $nextPaymentDate = $this->dateService->calculateNextBillingTime(
            $baseDate,
            $billingPeriod,
            $subscription->getCustomPeriodDays(),
            $subscription->getPreferredDay() ?? (int) $subscription->getStartDate()->format('j')
        );

        $subscription->setNextPaymentDate($nextPaymentDate);
        $subscription->addPayment($payment);

        $this->subscriptionRepository->save($subscription);

        return $payment;
    }

    public function deleteSubscription(Subscription $subscription): void
    {
        $this->subscriptionRepository->remove($subscription);
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        if (!$subscription->isActive()) {
            return;
        }

        $subscription->setCancelledAt(new \DateTime());
        $this->subscriptionRepository->save($subscription);
    }

    public function reactivateSubscription(Subscription $subscription): void
    {
        if ($subscription->isActive()) {
            return;
        }

        $subscription->setCancelledAt(null);
        $this->subscriptionRepository->save($subscription);
    }

    public function updateSubscription(Subscription $subscription, array $scheduleBefore): Subscription
    {
        $billingPeriod = BillingPeriodType::from($subscription->getBillingPeriod());

        if ($billingPeriod === BillingPeriodType::CUSTOM && !$subscription->getCustomPeriodDays()) {
            throw new \InvalidArgumentException('Для своего периода оплаты укажите количество дней');
        }

        $scheduleChanged =
            $this->datesDiffer($scheduleBefore['startDate'], $subscription->getStartDate())
            || $scheduleBefore['billingPeriod'] !== $subscription->getBillingPeriod()
            || $scheduleBefore['customPeriodDays'] !== $subscription->getCustomPeriodDays()
            || $scheduleBefore['preferredDay'] !== $subscription->getPreferredDay();

        if ($scheduleChanged) {
            $nextPaymentDate = $this->dateService->calculateNextBillingTime(
                $subscription->getStartDate() ?? new \DateTime(),
                $billingPeriod,
                $subscription->getCustomPeriodDays(),
                $subscription->getPreferredDay() ?? (int) ($subscription->getStartDate() ?? new \DateTime())->format('j')
            );
            $subscription->setNextPaymentDate($nextPaymentDate);
        }

        $this->subscriptionRepository->save($subscription);

        return $subscription;
    }

    private function datesDiffer(?\DateTime $a, ?\DateTime $b): bool
    {
        if ($a === null && $b === null) {
            return false;
        }
        if ($a === null || $b === null) {
            return true;
        }
        return $a->format('Y-m-d') !== $b->format('Y-m-d');
    }

    private function createPaymentRecord(Subscription $subscription, \DateTime $paidAt): SubscriptionPayment
    {
        $amount = $subscription->getAmount() ?? 0.0;
        $currency = $subscription->getCurrency() ?? 'RUB';

        $payment = new SubscriptionPayment();
        $payment->setPaidAt($paidAt);
        $payment->setAmount($amount);
        $payment->setCurrency($currency);
        $payment->setAmountInRub($this->currencyConversionService->convertToRub($amount, $currency));

        return $payment;
    }
}
