<?php

namespace App\Controller;

use App\Form\SubscriptionFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(SubscriptionFormType::class);

        $subscriptions =$user ? $user->getSubscriptions() : [];

        return $this->render('subscription/index.html.twig', [
            'form' => $form->createView(),
            'subscriptions' => $subscriptions,
        ]);
    }

    #[Route('/add', name: 'app_subscription_add')]
    public function add(): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(SubscriptionFormType::class);

        return $this->render('subscription/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
