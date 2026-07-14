<?php

namespace App\Tests\Command;

use App\Command\NotifyUpcomingPaymentsCommand;
use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use App\Service\PaymentReminderService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NotifyUpcomingPaymentsCommandTest extends TestCase
{
    private SubscriptionRepository $subscriptionRepository;
    private PaymentReminderService $paymentReminderService;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->paymentReminderService = $this->createMock(PaymentReminderService::class);

        $command = new NotifyUpcomingPaymentsCommand(
            $this->subscriptionRepository,
            $this->paymentReminderService
        );

        $application = new Application();
        $application->addCommand($command);

        $this->commandTester = new CommandTester($application->find('app:notify-upcoming-payments'));
    }

    private function createSubscriptionMock(
        int $id,
        ?\DateTime $reminderSentFor = null,
        ?\DateTime $nextPaymentDate = null
    ): Subscription {
        $state = [
            'id' => $id,
            'reminderSentFor' => $reminderSentFor,
            'nextPaymentDate' => $nextPaymentDate,
        ];

        $mock = $this->createMock(Subscription::class);
        $mock->method('getId')->willReturnCallback(fn() => $state['id']);
        $mock->method('getReminderSentFor')->willReturnCallback(fn() => $state['reminderSentFor']);
        $mock->method('getNextPaymentDate')->willReturnCallback(fn() => $state['nextPaymentDate']);
        $mock->method('setReminderSentFor')->willReturnCallback(function ($val) use (&$state, $mock) {
            $state['reminderSentFor'] = $val;
            return $mock;
        });

        return $mock;
    }

    public function testExecuteWithNoSubscriptions(): void
    {
        $this->subscriptionRepository
            ->expects($this->once())
            ->method('findDueForReminder')
            ->willReturn([]);

        $this->paymentReminderService
            ->expects($this->never())
            ->method('sendReminder');

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отправлено: 0', $output);
        $this->assertStringContainsString('пропущено', $output);
        $this->assertStringContainsString('ошибок: 0', $output);
    }

    public function testExecuteSendsReminderSuccessfully(): void
    {
        $nextPaymentDate = new \DateTime('+3 days');
        $subscription = $this->createSubscriptionMock(1, null, $nextPaymentDate);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('findDueForReminder')
            ->willReturn([$subscription]);

        $this->paymentReminderService
            ->expects($this->once())
            ->method('sendReminder')
            ->with($subscription);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save')
            ->with($subscription);

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отправлено: 1', $output);
        $this->assertStringContainsString('пропущено (уже отправлено ранее): 0', $output);
        $this->assertStringContainsString('ошибок: 0', $output);
    }

    public function testExecuteSkipsAlreadySentReminder(): void
    {
        $nextPaymentDate = new \DateTime('2026-07-20');
        $subscription = $this->createSubscriptionMock(1, clone $nextPaymentDate, $nextPaymentDate);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('findDueForReminder')
            ->willReturn([$subscription]);

        $this->paymentReminderService
            ->expects($this->never())
            ->method('sendReminder');

        $this->subscriptionRepository
            ->expects($this->never())
            ->method('save');

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отправлено: 0', $output);
        $this->assertStringContainsString('пропущено (уже отправлено ранее): 1', $output);
    }

    public function testExecuteSendsWhenReminderSentForDifferentDate(): void
    {
        $nextPaymentDate = new \DateTime('2026-07-20');
        $oldReminderDate = new \DateTime('2026-06-20');
        $subscription = $this->createSubscriptionMock(1, $oldReminderDate, $nextPaymentDate);

        $this->subscriptionRepository
            ->method('findDueForReminder')
            ->willReturn([$subscription]);

        $this->paymentReminderService
            ->expects($this->once())
            ->method('sendReminder')
            ->with($subscription);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отправлено: 1', $output);
    }

    public function testExecuteHandlesSendReminderException(): void
    {
        $nextPaymentDate = new \DateTime('+3 days');
        $subscription = $this->createSubscriptionMock(42, null, $nextPaymentDate);

        $this->subscriptionRepository
            ->method('findDueForReminder')
            ->willReturn([$subscription]);

        $this->paymentReminderService
            ->expects($this->once())
            ->method('sendReminder')
            ->willThrowException(new \RuntimeException('У подписки #42 нет пользователя с email'));

        $this->subscriptionRepository
            ->expects($this->never())
            ->method('save');

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отправлено: 0', $output);
        $this->assertStringContainsString('ошибок: 1', $output);
        $this->assertStringContainsString('#42', $output);
        $this->assertStringContainsString('нет пользователя с email', $output);
    }

    public function testExecuteMixedScenario(): void
    {
        $date1 = new \DateTime('+3 days');
        $date2 = new \DateTime('2026-07-20');

        $subToSend = $this->createSubscriptionMock(1, null, $date1);
        $subSkipped = $this->createSubscriptionMock(2, clone $date2, $date2); // уже отправлена
        $subFailed = $this->createSubscriptionMock(3, null, $date1);

        $this->subscriptionRepository
            ->method('findDueForReminder')
            ->willReturn([$subToSend, $subSkipped, $subFailed]);

        $callCount = 0;
        $this->paymentReminderService
            ->method('sendReminder')
            ->willReturnCallback(function ($sub) use (&$callCount) {
                $callCount++;
                if ($sub->getId() === 3) {
                    throw new \RuntimeException('Mailer error');
                }
            });

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save');

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Отправлено: 1', $output);
        $this->assertStringContainsString('пропущено (уже отправлено ранее): 1', $output);
        $this->assertStringContainsString('ошибок: 1', $output);
    }

    public function testExecuteSavesReminderSentForDate(): void
    {
        $nextPaymentDate = new \DateTime('2026-07-20 10:30:00');
        $state = ['reminderSentFor' => null];

        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn(1);
        $subscription->method('getReminderSentFor')->willReturnCallback(fn() => $state['reminderSentFor']);
        $subscription->method('getNextPaymentDate')->willReturn($nextPaymentDate);
        $subscription->method('setReminderSentFor')->willReturnCallback(function ($val) use (&$state, $subscription) {
            $state['reminderSentFor'] = $val;
            return $subscription;
        });

        $this->subscriptionRepository->method('findDueForReminder')->willReturn([$subscription]);
        $this->paymentReminderService->method('sendReminder');
        $this->subscriptionRepository->method('save');

        $this->commandTester->execute([]);

        $this->assertNotNull($state['reminderSentFor']);
        $this->assertEquals('2026-07-20', $state['reminderSentFor']->format('Y-m-d'));
        $this->assertNotSame($nextPaymentDate, $state['reminderSentFor']);
    }

    public function testCommandHasCorrectName(): void
    {
        $command = new NotifyUpcomingPaymentsCommand(
            $this->subscriptionRepository,
            $this->paymentReminderService
        );

        $this->assertEquals('app:notify-upcoming-payments', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = new NotifyUpcomingPaymentsCommand(
            $this->subscriptionRepository,
            $this->paymentReminderService
        );

        $this->assertNotEmpty($command->getDescription());
    }

    public function testExecutePassesCorrectTargetDateToRepository(): void
    {
        $this->subscriptionRepository
            ->expects($this->once())
            ->method('findDueForReminder')
            ->with($this->callback(function (\DateTime $date) {
                $expected = (new \DateTime())->modify('+3 days');
                return $date->format('Y-m-d') === $expected->format('Y-m-d');
            }))
            ->willReturn([]);

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }
}
