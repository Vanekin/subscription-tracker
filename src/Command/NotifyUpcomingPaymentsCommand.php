<?php

namespace App\Command;

use App\Repository\SubscriptionRepository;
use App\Service\PaymentReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notify-upcoming-payments',
    description: 'Отправляет напоминания о предстоящих платежах'
)]
class NotifyUpcomingPaymentsCommand extends Command
{
    private const REMINDER_DAYS_BEFORE = 3;

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentReminderService $paymentReminderService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $targetDate = (new \DateTime())->modify(sprintf('+%d days', self::REMINDER_DAYS_BEFORE));
        $subscriptions = $this->subscriptionRepository->findDueForReminder($targetDate);

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            if ($subscription->getReminderSentFor()
                && $subscription->getNextPaymentDate()
                && $subscription->getReminderSentFor()->format('Y-m-d') === $subscription->getNextPaymentDate()->format('Y-m-d')
            ) {
                $skipped++;
                continue;
            }

            try {
                $this->paymentReminderService->sendReminder($subscription);
                $subscription->setReminderSentFor(clone $subscription->getNextPaymentDate());
                $this->subscriptionRepository->save($subscription);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $io->warning(sprintf(
                    'Не удалось отправить напоминание по подписке #%d: %s',
                    $subscription->getId(),
                    $e->getMessage()
                ));
            }
        }

        $io->success(sprintf(
            'Готово. Отправлено: %d, пропущено (уже отправлено ранее): %d, ошибок: %d',
            $sent,
            $skipped,
            $failed
        ));

        return Command::SUCCESS;
    }
}
