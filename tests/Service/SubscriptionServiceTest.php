<?php

namespace App\Tests\Service;

use App\Entity\Subscription;
use App\Entity\SubscriptionPayment;
use App\Entity\User;
use App\Enum\BillingPeriodType;
use App\Repository\SubscriptionRepository;
use App\Service\CurrencyConversionService;
use App\Service\DateService;
use App\Service\SubscriptionService;
use PHPUnit\Framework\TestCase;

class SubscriptionServiceTest extends TestCase
{
    private SubscriptionRepository $subscriptionRepository;
    private DateService $dateService;
    private CurrencyConversionService $currencyConversionService;
    private SubscriptionService $service;

    protected function setUp(): void
    {
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->dateService = $this->createMock(DateService::class);
        $this->currencyConversionService = $this->createMock(CurrencyConversionService::class);

        $this->service = new SubscriptionService(
            $this->subscriptionRepository,
            $this->dateService,
            $this->currencyConversionService
        );
    }

    private function createSubscriptionMock(array $methods = []): Subscription
    {
        $defaults = [
            'getId' => 1,
            'getUser' => null,
            'getStartDate' => new \DateTime('2026-07-13'),
            'getBillingPeriod' => 'monthly',
            'getCustomPeriodDays' => null,
            'getPreferredDay' => null,
            'getAmount' => 500.0,
            'getCurrency' => 'RUB',
            'getName' => 'Test Subscription',
            'getNextPaymentDate' => null,
            'isActive' => true,
            'setUser' => null,
            'setStartDate' => null,
            'setNextPaymentDate' => null,
            'addPayment' => null,
            'setCancelledAt' => null,
            'getCancelledAt' => null,
        ];

        $merged = array_merge($defaults, $methods);

        $subscription = $this->createMock(Subscription::class);
        foreach ($merged as $method => $value) {
            if ($value !== null || in_array($method, ['getUser', 'getStartDate', 'getCustomPeriodDays', 'getPreferredDay', 'getAmount', 'getCurrency', 'getNextPaymentDate', 'getCancelledAt'])) {
                $subscription->method($method)->willReturn($value);
            }
        }

        return $subscription;
    }

    private function createUserMock(int $id = 1, ?string $email = 'user@example.com'): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getEmail')->willReturn($email);
        return $user;
    }

    // ==================== saveNewSubscription ====================

    public function testSaveNewSubscriptionSetsUserAndStartDate(): void
    {
        // Arrange
        $user = $this->createUserMock();

        // Используем РЕАЛЬНЫЙ объект Subscription.
        // Не вызываем setStartDate/setUser — они по умолчанию null,
        // и сервис сам их установит.
        $subscription = new Subscription();
        $subscription->setBillingPeriod('monthly');
        $subscription->setAmount(500.0);
        $subscription->setCurrency('RUB');
        $subscription->setName('Test Subscription');

        $this->dateService
            ->expects($this->once())
            ->method('calculateNextBillingTime')
            ->with(
                $this->isInstanceOf(\DateTime::class),
                BillingPeriodType::MONTHLY,
                null,
                $this->isType('int')
            )
            ->willReturn(new \DateTime('2026-08-13'));

        $this->currencyConversionService
            ->expects($this->once())
            ->method('convertToRub')
            ->with(500.0, 'RUB')
            ->willReturn(500.0);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save')
            ->with($subscription);

        $result = $this->service->saveNewSubscription($subscription, $user);

        $this->assertSame($subscription, $result);
        $this->assertSame($user, $subscription->getUser());
        $this->assertInstanceOf(\DateTime::class, $subscription->getStartDate());
        $this->assertInstanceOf(\DateTime::class, $subscription->getNextPaymentDate());
    }

    public function testSaveNewSubscriptionPreservesExistingStartDate(): void
    {
        $user = $this->createUserMock();
        $existingStartDate = new \DateTime('2026-01-01');

        $subscription = $this->createSubscriptionMock([
            'getStartDate' => $existingStartDate,
            'getBillingPeriod' => 'monthly',
        ]);

        $subscription->expects($this->never())->method('setStartDate');

        $this->dateService
            ->expects($this->once())
            ->method('calculateNextBillingTime')
            ->with($existingStartDate, BillingPeriodType::MONTHLY, null, 1)
            ->willReturn(new \DateTime('2026-02-01'));

        $this->subscriptionRepository->expects($this->once())->method('save');

        $this->service->saveNewSubscription($subscription, $user);

    }

    public function testSaveNewSubscriptionThrowsExceptionForCustomPeriodWithoutDays(): void
    {
        // Arrange
        $user = $this->createUserMock();
        $subscription = $this->createSubscriptionMock([
            'getBillingPeriod' => 'custom',
            'getCustomPeriodDays' => null,
        ]);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Для своего периода оплаты укажите количество дней');

        $this->service->saveNewSubscription($subscription, $user);
    }

    public function testSaveNewSubscriptionUsesPreferredDay(): void
    {
        // Arrange
        $user = $this->createUserMock();
        $subscription = $this->createSubscriptionMock([
            'getBillingPeriod' => 'monthly',
            'getPreferredDay' => 25,
        ]);

        $this->dateService
            ->expects($this->once())
            ->method('calculateNextBillingTime')
            ->with($this->anything(), BillingPeriodType::MONTHLY, null, 25)
            ->willReturn(new \DateTime('2026-08-25'));

        $this->subscriptionRepository->expects($this->once())->method('save');

        // Act
        $this->service->saveNewSubscription($subscription, $user);

        // Assert
        // preferredDay передан в calculateNextBillingTime
    }

    public function testSaveNewSubscriptionUsesStartDateDayAsPreferredDay(): void
    {
        // Arrange
        $user = $this->createUserMock();
        $startDate = new \DateTime('2026-07-17');

        $subscription = $this->createSubscriptionMock([
            'getStartDate' => $startDate,
            'getBillingPeriod' => 'monthly',
            'getPreferredDay' => null,
        ]);

        $this->dateService
            ->expects($this->once())
            ->method('calculateNextBillingTime')
            ->with($startDate, BillingPeriodType::MONTHLY, null, 17)
            ->willReturn(new \DateTime('2026-08-17'));

        $this->subscriptionRepository->expects($this->once())->method('save');

        // Act
        $this->service->saveNewSubscription($subscription, $user);

    }

    public function testSaveNewSubscriptionCreatesPaymentRecord(): void
    {
        // Arrange
        $user = $this->createUserMock();
        $subscription = $this->createSubscriptionMock([
            'getBillingPeriod' => 'monthly',
            'getAmount' => 1000.0,
            'getCurrency' => 'USD',
        ]);

        $this->currencyConversionService
            ->expects($this->once())
            ->method('convertToRub')
            ->with(1000.0, 'USD')
            ->willReturn(90000.0);

        $this->subscriptionRepository->expects($this->once())->method('save');

        // Act
        $this->service->saveNewSubscription($subscription, $user);

    }


    public function testGetUserSubscriptionReturnsNullWhenNoUser(): void
    {
        // Arrange
        $this->subscriptionRepository
            ->expects($this->never())
            ->method('getById');

        // Act
        $result = $this->service->getUserSubscription(1, null);

        // Assert
        $this->assertNull($result);
    }

    public function testGetUserSubscriptionReturnsNullWhenSubscriptionNotFound(): void
    {
        // Arrange
        $user = $this->createUserMock();

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('getById')
            ->with(999)
            ->willReturn(null);

        // Act
        $result = $this->service->getUserSubscription(999, $user);

        // Assert
        $this->assertNull($result);
    }

    public function testGetUserSubscriptionReturnsNullWhenSubscriptionBelongsToAnotherUser(): void
    {
        // Arrange
        $user = $this->createUserMock(1);
        $otherUser = $this->createUserMock(2);

        $subscription = $this->createSubscriptionMock([
            'getUser' => $otherUser,
        ]);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($subscription);

        // Act
        $result = $this->service->getUserSubscription(1, $user);

        // Assert
        $this->assertNull($result);
    }

    public function testGetUserSubscriptionReturnsSubscriptionWhenBelongsToUser(): void
    {
        // Arrange
        $user = $this->createUserMock(1);

        $subscription = $this->createSubscriptionMock([
            'getUser' => $user,
        ]);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($subscription);

        // Act
        $result = $this->service->getUserSubscription(1, $user);

        // Assert
        $this->assertSame($subscription, $result);
    }

    public function testGetUserSubscriptionReturnsNullWhenSubscriptionHasNoUser(): void
    {
        // Arrange
        $user = $this->createUserMock(1);

        $subscription = $this->createSubscriptionMock([
            'getUser' => null,
        ]);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('getById')
            ->willReturn($subscription);

        // Act
        $result = $this->service->getUserSubscription(1, $user);

        // Assert
        $this->assertNull($result);
    }


    public function testMarkAsPaidThrowsExceptionWhenSubscriptionNotActive(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock([
            'isActive' => false,
        ]);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Подписка завершена - отметить оплату нельзя');

        $this->service->markAsPaid($subscription);
    }

    public function testMarkAsPaidCreatesPaymentAndShiftsNextPaymentDate(): void
    {
        // Arrange
        $nextPaymentDate = new \DateTime('2026-08-13');
        $newNextPaymentDate = new \DateTime('2026-09-13');

        $subscription = $this->createSubscriptionMock([
            'isActive' => true,
            'getBillingPeriod' => 'monthly',
            'getNextPaymentDate' => $nextPaymentDate,
            'getAmount' => 500.0,
            'getCurrency' => 'RUB',
            'getStartDate' => new \DateTime('2026-07-13'),
        ]);

        $this->currencyConversionService
            ->expects($this->once())
            ->method('convertToRub')
            ->with(500.0, 'RUB')
            ->willReturn(500.0);

        $this->dateService
            ->expects($this->once())
            ->method('calculateNextBillingTime')
            ->with($nextPaymentDate, BillingPeriodType::MONTHLY, null, 13)
            ->willReturn($newNextPaymentDate);

        $subscription->expects($this->once())->method('setNextPaymentDate')->with($newNextPaymentDate);
        $subscription->expects($this->once())->method('addPayment');

        $this->subscriptionRepository->expects($this->once())->method('save');

        // Act
        $result = $this->service->markAsPaid($subscription);

        // Assert
        $this->assertInstanceOf(SubscriptionPayment::class, $result);
    }

    public function testMarkAsPaidUsesCurrentDateWhenNoNextPaymentDate(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock([
            'isActive' => true,
            'getBillingPeriod' => 'monthly',
            'getNextPaymentDate' => null,
            'getAmount' => 100.0,
            'getCurrency' => 'RUB',
            'getStartDate' => new \DateTime('2026-07-13'),
        ]);

        $this->dateService
            ->expects($this->once())
            ->method('calculateNextBillingTime')
            ->with($this->callback(function (\DateTime $date) {
                // Должна быть использована текущая дата (в пределах секунды)
                $now = new \DateTime();
                return abs($date->getTimestamp() - $now->getTimestamp()) < 2;
            }), BillingPeriodType::MONTHLY, null, 13)
            ->willReturn(new \DateTime('2026-08-13'));

        $this->subscriptionRepository->expects($this->once())->method('save');

        // Act
        $this->service->markAsPaid($subscription);

    }

    public function testMarkAsPaidCreatesPaymentWithCurrentDate(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock([
            'isActive' => true,
            'getBillingPeriod' => 'monthly',
            'getNextPaymentDate' => new \DateTime('2026-08-13'),
            'getAmount' => 200.0,
            'getCurrency' => 'USD',
            'getStartDate' => new \DateTime('2026-07-13'),
        ]);

        $this->currencyConversionService
            ->expects($this->once())
            ->method('convertToRub')
            ->with(200.0, 'USD')
            ->willReturn(18000.0);

        $this->dateService
            ->method('calculateNextBillingTime')
            ->willReturn(new \DateTime('2026-09-13'));

        $this->subscriptionRepository->expects($this->once())->method('save');

        // Act
        $payment = $this->service->markAsPaid($subscription);

        // Assert
        $this->assertInstanceOf(SubscriptionPayment::class, $payment);
    }


    public function testDeleteSubscriptionCallsRepositoryRemove(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock();

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('remove')
            ->with($subscription);

        // Act
        $this->service->deleteSubscription($subscription);

    }


    public function testCancelSubscriptionDoesNothingWhenAlreadyCancelled(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock([
            'isActive' => false,
        ]);

        $subscription->expects($this->never())->method('setCancelledAt');
        $this->subscriptionRepository->expects($this->never())->method('save');

        // Act
        $this->service->cancelSubscription($subscription);

    }

    public function testCancelSubscriptionSetsCancelledAtAndSaves(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock([
            'isActive' => true,
        ]);

        $subscription->expects($this->once())
            ->method('setCancelledAt')
            ->with($this->isInstanceOf(\DateTime::class));

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save')
            ->with($subscription);

        // Act
        $this->service->cancelSubscription($subscription);

    }


    public function testReactivateSubscriptionDoesNothingWhenAlreadyActive(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock([
            'isActive' => true,
        ]);

        $subscription->expects($this->never())->method('setCancelledAt');
        $this->subscriptionRepository->expects($this->never())->method('save');

        // Act
        $this->service->reactivateSubscription($subscription);

    }

    public function testReactivateSubscriptionClearsCancelledAtAndSaves(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock([
            'isActive' => false,
        ]);

        $subscription->expects($this->once())
            ->method('setCancelledAt')
            ->with(null);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save')
            ->with($subscription);

        // Act
        $this->service->reactivateSubscription($subscription);

    }


    public function testUpdateSubscriptionRecalculatesNextPaymentDateWhenStartDateChanged(): void
    {
        // Arrange
        $originalStartDate = new \DateTime('2026-01-01');
        $newStartDate = new \DateTime('2026-07-13');

        $scheduleBefore = ['startDate' => $originalStartDate, 'billingPeriod' => 'monthly', 'customPeriodDays' => null, 'preferredDay' => null];

        $subscription = $this->createSubscriptionMock([
            'getBillingPeriod' => 'monthly',
            'getStartDate' => $newStartDate,
            'getCustomPeriodDays' => null,
            'getPreferredDay' => null,
        ]);

        $this->dateService
            ->expects($this->once())
            ->method('calculateNextBillingTime')
            ->with($newStartDate, BillingPeriodType::MONTHLY, null, 13)
            ->willReturn(new \DateTime('2026-08-13'));

        $subscription->expects($this->once())->method('setNextPaymentDate');

        $this->subscriptionRepository->expects($this->once())->method('save');

        // Act
        $result = $this->service->updateSubscription($subscription, $scheduleBefore);

        // Assert
        $this->assertSame($subscription, $result);
    }

    public function testUpdateSubscriptionDoesNotRecalculateWhenStartDateUnchanged(): void
    {
        // Arrange
        $startDate = new \DateTime('2026-07-13');

        $scheduleBefore = ['startDate' => $startDate, 'billingPeriod' => 'monthly', 'customPeriodDays' => null, 'preferredDay' => null];

        $subscription = $this->createSubscriptionMock([
            'getBillingPeriod' => 'monthly',
            'getStartDate' => $startDate,
            'getCustomPeriodDays' => null,
            'getPreferredDay' => null,
        ]);

        $this->dateService
            ->expects($this->never())
            ->method('calculateNextBillingTime');

        $subscription->expects($this->never())->method('setNextPaymentDate');

        $this->subscriptionRepository->expects($this->once())->method('save');

        // Act
        $this->service->updateSubscription($subscription, $scheduleBefore);

    }

    public function testUpdateSubscriptionRecalculatesWhenOriginalStartDateIsNull(): void
    {
        // Arrange
        $newStartDate = new \DateTime('2026-07-13');
        $startDate = new \DateTime('2026-07-11');

        $subscription = $this->createSubscriptionMock([
            'getBillingPeriod' => 'monthly',
            'getStartDate' => $newStartDate,
            'getCustomPeriodDays' => null,
            'getPreferredDay' => null,
        ]);
        $scheduleBefore = ['startDate' => $startDate, 'billingPeriod' => 'monthly', 'customPeriodDays' => null, 'preferredDay' => null];

        $this->dateService
            ->expects($this->once())
            ->method('calculateNextBillingTime');

        $this->subscriptionRepository->expects($this->once())->method('save');

        // Act
        $this->service->updateSubscription($subscription, $scheduleBefore);

    }

    public function testUpdateSubscriptionThrowsExceptionForCustomPeriodWithoutDays(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock([
            'getBillingPeriod' => 'custom',
            'getStartDate' => new \DateTime('2026-07-13'),
            'getCustomPeriodDays' => null,
            'getPreferredDay' => null,

        ]);

        $scheduleBefore = ['startDate' => new \DateTime(), 'billingPeriod' => 'monthly', 'customPeriodDays' => null, 'preferredDay' => null];


        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Для своего периода оплаты укажите количество дней');

        $this->service->updateSubscription($subscription, $scheduleBefore);
    }

    public function testUpdateSubscriptionUsesPreferredDayWhenRecalculating(): void
    {
        // Arrange
        $originalStartDate = new \DateTime('2026-01-01');
        $newStartDate = new \DateTime('2026-07-13');

        $subscription = $this->createSubscriptionMock([
            'getBillingPeriod' => 'monthly',
            'getStartDate' => $newStartDate,
            'getPreferredDay' => 20,
            'getCustomPeriodDays' => null,
        ]);

        $scheduleBefore = ['startDate' => $newStartDate, 'billingPeriod' => 'monthly', 'customPeriodDays' => null, 'preferredDay' => 21];

        $this->dateService
            ->expects($this->once())
            ->method('calculateNextBillingTime')
            ->with($newStartDate, BillingPeriodType::MONTHLY, null, 20)
            ->willReturn(new \DateTime('2026-08-20'));

        $this->subscriptionRepository->expects($this->once())->method('save');

        // Act
        $this->service->updateSubscription($subscription, $scheduleBefore);

    }

    public function testUpdateSubscriptionHandlesNullStartDate(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock([
            'getBillingPeriod' => 'monthly',
            'getStartDate' => new \DateTime('2026-01-01'),
            'getPreferredDay' => null,
            'getCustomPeriodDays' => null,
        ]);

        $scheduleBefore = ['startDate' => null, 'billingPeriod' => 'monthly', 'customPeriodDays' => null, 'preferredDay' => null];

        $this->dateService
            ->expects($this->once())
            ->method('calculateNextBillingTime')
            ->with($this->isInstanceOf(\DateTime::class), BillingPeriodType::MONTHLY, null, $this->anything())
            ->willReturn(new \DateTime('2026-08-13'));

        $this->subscriptionRepository->expects($this->once())->method('save');

        // Act
        $this->service->updateSubscription($subscription, $scheduleBefore);

    }

    public function testUpdateSubscriptionDoesNotRecalculateWhenBothStartDatesAreNull(): void
    {
        // Подписка с null startDate
        $subscription = $this->createSubscriptionMock([
            'getBillingPeriod' => 'monthly',
            'getStartDate' => null,
            'getCustomPeriodDays' => null,
            'getPreferredDay' => null,
        ]);

        // scheduleBefore с null startDate и остальными параметрами такими же, как у подписки
        $scheduleBefore = [
            'startDate' => null,
            'billingPeriod' => 'monthly',
            'customPeriodDays' => null,
            'preferredDay' => null,
        ];

        // Ожидаем, что пересчёт НЕ будет вызван
        $this->dateService
            ->expects($this->never())
            ->method('calculateNextBillingTime');

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save');

        // Act
        $this->service->updateSubscription($subscription, $scheduleBefore);
    }
}

