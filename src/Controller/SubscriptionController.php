<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Form\SubscriptionFormType;
use App\Service\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\SubscriptionPaymentRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly SubscriptionPaymentRepository $subscriptionPaymentRepository,
    ){}
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();

        $subscriptions =$user ? $user->getSubscriptions() : [];

        return $this->render('subscription/index.html.twig', [
            'subscriptions' => $subscriptions,
            'user' => $user,
        ]);
    }

    #[Route('/add', name: 'app_subscription_add')]
    public function add(Request $request): Response
    {
        $user = $this->getUser();

        $subscription = new Subscription();
        $subscription->setStartDate(new \DateTime());
        $form = $this->createForm(SubscriptionFormType::class, $subscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->subscriptionService->saveNewSubscription($subscription, $user);
                $this->addFlash('success', sprintf('Подписка успешно добавлена! %s', $subscription->getName()));
                return $this->redirectToRoute('app_home');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (\RuntimeException $e) {
                $this->addFlash('error', 'Не удалось добавить подписку. Попробуйте позже.');
            }
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }


        return $this->render('subscription/add.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/my-subscriptions', name: 'app_my_subscriptions')]
    public function subIndex(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('user_login');
        }

        $monthlyStats = $this->subscriptionPaymentRepository->getMonthlyStatsForUser($user);

        return $this->render('subscription/my_subscriptions_index.html.twig', [
            'subscriptions' => $user->getSubscriptions(),
            'monthlyStats' => $monthlyStats,
        ]);
    }

    #[Route('/my-subscriptions/{id}', name: 'app_my_subscription_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $subscription = $this->subscriptionService->getUserSubscription($id, $this->getUser());

        if (!$subscription) {
            throw $this->createNotFoundException('Подписка не найдена');
        }

        return $this->render('subscription/my_subscriptions_show.html.twig', [
            'subscription' => $subscription,
        ]);
    }

    #[Route('/my-subscriptions/{id}/pay', name: 'app_my_subscription_pay', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markAsPaid(int $id, Request $request): RedirectResponse
    {
        $subscription = $this->subscriptionService->getUserSubscription($id, $this->getUser());

        if (!$subscription) {
            throw $this->createNotFoundException('Подписка не найдена');
        }

        if (!$this->isCsrfTokenValid('pay' . $subscription->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен, попробуйте ещё раз.');

            return $this->redirectToRoute('app_my_subscription_show', ['id' => $id]);
        }

        try {
            $this->subscriptionService->markAsPaid($subscription);
            $this->addFlash('success', 'Оплата отмечена, дата следующего платежа обновлена.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Не удалось отметить оплату: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_my_subscription_show', ['id' => $id]);
    }

    #[Route('/my-subscriptions/{id}/edit', name: 'app_my_subscription_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $subscription = $this->subscriptionService->getUserSubscription($id, $this->getUser());

        if (!$subscription) {
            throw $this->createNotFoundException('Подписка не найдена');
        }

        $scheduleBefore = [
            'startDate' => $subscription->getStartDate() ? clone $subscription->getStartDate() : null,
            'billingPeriod' => $subscription->getBillingPeriod(),
            'customPeriodDays' => $subscription->getCustomPeriodDays(),
            'preferredDay' => $subscription->getPreferredDay(),
        ];

        $form = $this->createForm(SubscriptionFormType::class, $subscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->subscriptionService->updateSubscription($subscription, $scheduleBefore);
                $this->addFlash('success', 'Подписка обновлена.');

                return $this->redirectToRoute('app_my_subscription_show', ['id' => $id]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('subscription/my_subscriptions_edit.html.twig', [
            'form' => $form->createView(),
            'subscription' => $subscription,
        ]);
    }

    #[Route('/my-subscriptions/{id}/cancel', name: 'app_my_subscription_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, Request $request): RedirectResponse
    {
        $subscription = $this->subscriptionService->getUserSubscription($id, $this->getUser());

        if (!$subscription) {
            throw $this->createNotFoundException('Подписка не найдена');
        }

        if (!$this->isCsrfTokenValid('cancel' . $subscription->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен, попробуйте ещё раз.');

            return $this->redirectToRoute('app_my_subscription_show', ['id' => $id]);
        }

        $this->subscriptionService->cancelSubscription($subscription);
        $this->addFlash('success', 'Подписка завершена. История платежей сохранена.');

        return $this->redirectToRoute('app_my_subscription_show', ['id' => $id]);
    }

    #[Route('/my-subscriptions/{id}/reactivate', name: 'app_my_subscription_reactivate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reactivate(int $id, Request $request): RedirectResponse
    {
        $subscription = $this->subscriptionService->getUserSubscription($id, $this->getUser());

        if (!$subscription) {
            throw $this->createNotFoundException('Подписка не найдена');
        }

        if (!$this->isCsrfTokenValid('reactivate' . $subscription->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен, попробуйте ещё раз.');

            return $this->redirectToRoute('app_my_subscription_show', ['id' => $id]);
        }

        $this->subscriptionService->reactivateSubscription($subscription);
        $this->addFlash('success', 'Подписка возобновлена.');

        return $this->redirectToRoute('app_my_subscription_show', ['id' => $id]);
    }

    #[Route('/my-subscriptions/{id}/delete', name: 'app_my_subscription_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): RedirectResponse
    {
        $subscription = $this->subscriptionService->getUserSubscription($id, $this->getUser());

        if (!$subscription) {
            throw $this->createNotFoundException('Подписка не найдена');
        }

        if (!$this->isCsrfTokenValid('delete' . $subscription->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен, попробуйте ещё раз.');

            return $this->redirectToRoute('app_my_subscription_show', ['id' => $id]);
        }

        $this->subscriptionService->deleteSubscription($subscription);
        $this->addFlash('success', 'Подписка удалена.');

        return $this->redirectToRoute('app_my_subscriptions');
    }
}
