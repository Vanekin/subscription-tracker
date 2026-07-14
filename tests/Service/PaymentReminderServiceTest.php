<?php

namespace App\Tests\Service;

use App\Entity\Subscription;
use App\Entity\User;
use App\Service\PaymentReminderService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PaymentReminderServiceTest extends TestCase
{
    private MailerInterface $mailer;
    private PaymentReminderService $service;
    private string $mailerFromAddress;

    protected function setUp(): void
    {
        $this->mailerFromAddress = 'noreply@example.com';
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->service = new PaymentReminderService(
            $this->mailer,
            $this->mailerFromAddress
        );
    }

    private function createSubscriptionMock(
        int $id,
        ?User $user,
        string $name,
        float $amount,
        string $currency,
        ?\DateTime $nextPaymentDate
    ): Subscription {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn($id);
        $subscription->method('getUser')->willReturn($user);
        $subscription->method('getName')->willReturn($name);
        $subscription->method('getAmount')->willReturn($amount);
        $subscription->method('getCurrency')->willReturn($currency);
        $subscription->method('getNextPaymentDate')->willReturn($nextPaymentDate);
        return $subscription;
    }

    private function createUserMock(?string $email): User
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn($email);
        return $user;
    }

    public function testSendReminderSendsEmailSuccessfully(): void
    {
        // Arrange
        $user = $this->createUserMock('user@example.com');
        $subscription = $this->createSubscriptionMock(
            123,
            $user,
            'Premium Plan',
            999.99,
            'RUB',
            new \DateTime('+3 days')
        );

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'user@example.com'
                    && $email->getFrom()[0]->getAddress() === 'noreply@example.com'
                    && str_contains($email->getSubject(), 'Premium Plan');
            }));

        // Act
        $this->service->sendReminder($subscription);

        // Assert
        // Expectation set in Arrange
    }

    public function testSendReminderThrowsExceptionWhenNoUser(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock(
            456,
            null,
            'Test',
            100,
            'RUB',
            new \DateTime('+3 days')
        );

        $this->mailer
            ->expects($this->never())
            ->method('send');

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('У подписки #456 нет пользователя с email - письмо не отправлено');

        $this->service->sendReminder($subscription);
    }

    public function testSendReminderThrowsExceptionWhenUserHasNoEmail(): void
    {
        // Arrange
        $user = $this->createUserMock(null);
        $subscription = $this->createSubscriptionMock(
            789,
            $user,
            'Test',
            100,
            'RUB',
            new \DateTime('+3 days')
        );

        $this->mailer
            ->expects($this->never())
            ->method('send');

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('У подписки #789 нет пользователя с email - письмо не отправлено');

        $this->service->sendReminder($subscription);
    }

    public function testSendReminderThrowsExceptionWhenUserHasEmptyEmail(): void
    {
        // Arrange
        $user = $this->createUserMock('');
        $subscription = $this->createSubscriptionMock(
            790,
            $user,
            'Test',
            100,
            'RUB',
            new \DateTime('+3 days')
        );

        $this->mailer
            ->expects($this->never())
            ->method('send');

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('У подписки #790 нет пользователя с email - письмо не отправлено');

        $this->service->sendReminder($subscription);
    }

    public function testSendReminderEmailContent(): void
    {
        // Arrange
        $user = $this->createUserMock('test@example.com');
        $nextPaymentDate = new \DateTime('2026-07-20');

        $subscription = $this->createSubscriptionMock(
            1,
            $user,
            'Basic Plan',
            500.00,
            'USD',
            $nextPaymentDate
        );

        $emailSent = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$emailSent) {
                $emailSent = $email;
            });

        // Act
        $this->service->sendReminder($subscription);

        // Assert
        $this->assertNotNull($emailSent);
        $this->assertEquals('noreply@example.com', $emailSent->getFrom()[0]->getAddress());
        $this->assertEquals('test@example.com', $emailSent->getTo()[0]->getAddress());
        $this->assertStringContainsString('Basic Plan', $emailSent->getSubject());

        $body = $emailSent->getTextBody();
        $this->assertStringContainsString('20.07.2026', $body);
        $this->assertStringContainsString('Basic Plan', $body);
        $this->assertStringContainsString('500', $body);
        $this->assertStringContainsString('USD', $body);
    }

    public function testSendReminderSubjectContainsSubscriptionName(): void
    {
        // Arrange
        $user = $this->createUserMock('user@example.com');
        $subscription = $this->createSubscriptionMock(
            10,
            $user,
            'Super Mega Plan',
            1500,
            'RUB',
            new \DateTime('2026-08-01')
        );

        $emailSent = null;
        $this->mailer
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$emailSent) {
                $emailSent = $email;
            });

        // Act
        $this->service->sendReminder($subscription);

        // Assert
        $this->assertStringContainsString('Super Mega Plan', $emailSent->getSubject());
        $this->assertStringContainsString('Через 3 дня спишется оплата', $emailSent->getSubject());
    }

    public function testSendReminderBodyContainsAllSubscriptionDetails(): void
    {
        // Arrange
        $user = $this->createUserMock('user@example.com');
        $nextPaymentDate = new \DateTime('2026-12-15');
        $subscription = $this->createSubscriptionMock(
            20,
            $user,
            'Yearly Pro',
            12000.50,
            'EUR',
            $nextPaymentDate
        );

        $emailSent = null;
        $this->mailer
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$emailSent) {
                $emailSent = $email;
            });

        // Act
        $this->service->sendReminder($subscription);

        // Assert
        $body = $emailSent->getTextBody();
        $this->assertStringContainsString('15.12.2026', $body);
        $this->assertStringContainsString('Yearly Pro', $body);
        $this->assertStringContainsString('12000.5', $body);
        $this->assertStringContainsString('EUR', $body);
        $this->assertStringContainsString('Здравствуйте!', $body);
        $this->assertStringContainsString('отметьте это на сайте', $body);
    }

    public function testSendReminderWithNullNextPaymentDateUsesDash(): void
    {
        // Arrange
        $user = $this->createUserMock('user@example.com');
        $subscription = $this->createSubscriptionMock(
            30,
            $user,
            'Test Sub',
            100,
            'RUB',
            null
        );

        $emailSent = null;
        $this->mailer
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$emailSent) {
                $emailSent = $email;
            });

        // Act
        $this->service->sendReminder($subscription);

        // Assert
        $this->assertStringContainsString('-', $emailSent->getTextBody());
    }

    public function testSendReminderUsesConfiguredFromAddress(): void
    {
        // Arrange
        $this->service = new PaymentReminderService(
            $this->mailer,
            'custom-from@example.com'
        );

        $user = $this->createUserMock('user@example.com');
        $subscription = $this->createSubscriptionMock(
            40,
            $user,
            'Test',
            100,
            'RUB',
            new \DateTime('+1 day')
        );

        $emailSent = null;
        $this->mailer
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$emailSent) {
                $emailSent = $email;
            });

        // Act
        $this->service->sendReminder($subscription);

        // Assert
        $this->assertEquals('custom-from@example.com', $emailSent->getFrom()[0]->getAddress());
    }

    public function testSendReminderExceptionMessageContainsSubscriptionId(): void
    {
        // Arrange
        $subscription = $this->createSubscriptionMock(
            999,
            null,
            'Test',
            100,
            'RUB',
            new \DateTime('+1 day')
        );

        // Act & Assert
        try {
            $this->service->sendReminder($subscription);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('999', $e->getMessage());
        }
    }
}
