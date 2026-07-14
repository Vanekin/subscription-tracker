<?php

namespace App\Service;

use App\Entity\Subscription;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PaymentReminderService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailerFromAddress,
    ) {
    }
    public function sendReminder(Subscription $subscription): void
    {
        $user = $subscription->getUser();

        if (!$user || !$user->getEmail()) {
            throw new \RuntimeException(sprintf(
                'У подписки #%d нет пользователя с email - письмо не отправлено',
                $subscription->getId()
            ));
        }

        $email = (new Email())
            ->from($this->mailerFromAddress)
            ->to($user->getEmail())
            ->subject(sprintf('Через 3 дня спишется оплата за "%s"', $subscription->getName()))
            ->text($this->buildBody($subscription));

        $this->mailer->send($email);
    }

    private function buildBody(Subscription $subscription): string
    {
        return sprintf(
            "Здравствуйте!\n\n".
            "Напоминаем: %s наступает срок оплаты подписки \"%s\" на сумму %s %s.\n\n".
            "Если вы уже оплатили или продлили подписку - отметьте это на сайте, чтобы обновить график платежей.\n",
            $subscription->getNextPaymentDate()?->format('d.m.Y') ?? '-',
            $subscription->getName(),
            $subscription->getAmount(),
            $subscription->getCurrency()
        );
    }
}
