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

        // billingPeriod хранит value enum'а (например 'monthly'), т.к. именно value
        // отправляет ChoiceType в форме — поэтому парсим через from(), а не по названию
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

        // Оплата за стартовый период - датируем её startDate. Без этого создание
        // подписки выглядело так, будто первый период достался бесплатно: nextPaymentDate
        // уже сдвигался вперёд, а сам факт оплаты нигде не фиксировался и не попадал
        // ни в историю платежей, ни в статистику по месяцам
        $initialPayment = $this->createPaymentRecord($subscription, $subscription->getStartDate());
        $subscription->addPayment($initialPayment);

        // cascade: ['persist'] на Subscription::$payments сохранит и платёж тоже
        $this->subscriptionRepository->save($subscription);

        return $subscription;
    }

    /**
     * Возвращает подписку по id, только если она принадлежит указанному пользователю
     * (защита от подмены id в URL - чужую подписку получить нельзя).
     */
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

    /**
     * Отмечает подписку оплаченной: фиксирует платёж в истории (с конвертацией
     * в рубли по текущему курсу ЦБ) и сдвигает дату следующего платежа вперёд.
     */
    public function markAsPaid(Subscription $subscription): SubscriptionPayment
    {
        $payment = $this->createPaymentRecord($subscription, new \DateTime());

        $billingPeriod = BillingPeriodType::from($subscription->getBillingPeriod());

        // Считаем от текущей даты следующего платежа (а не от "сегодня"),
        // чтобы не терять регулярность цикла, даже если пользователь отметил оплату с опозданием
        $baseDate = $subscription->getNextPaymentDate() ?? new \DateTime();

        $nextPaymentDate = $this->dateService->calculateNextBillingTime(
            $baseDate,
            $billingPeriod,
            $subscription->getCustomPeriodDays(),
            $subscription->getPreferredDay() ?? (int) $subscription->getStartDate()->format('j')
        );

        $subscription->setNextPaymentDate($nextPaymentDate);
        $subscription->addPayment($payment);

        // cascade: ['persist'] на Subscription::$payments сохранит и платёж тоже
        $this->subscriptionRepository->save($subscription);

        return $payment;
    }

    public function deleteSubscription(Subscription $subscription): void
    {
        $this->subscriptionRepository->remove($subscription);
    }

    /**
     * Сохраняет изменения существующей подписки.
     *
     * Дата следующего платежа пересчитывается автоматически ТОЛЬКО если пользователь
     * поменял дату начала - в этом случае график, очевидно, должен сдвинуться под неё.
     * Если дата начала осталась прежней (пользователь просто поправил название, цену
     * и т.п.), дата следующего платежа не трогается - оплата продолжает происходить
     * в те же дни, что и до редактирования.
     */
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

    /**
     * Создаёт (но не сохраняет) запись об оплате: сумма и валюта копируются
     * из подписки на текущий момент, плюс сразу считается сумма в рублях по курсу ЦБ.
     */
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
