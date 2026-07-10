<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserRegisterFormType;
use App\Repository\UserRepository;
use App\Service\UserService;
use Cassandra\Type\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserService $userService,
    )
    {
    }

    #[Route('/register', name: 'user_register')]
    public function register(
        Request $request,
        UserAuthenticatorInterface $userAuthenticator,
        FormLoginAuthenticator $authenticator,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        $form = $this->createForm(UserRegisterFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = $this->userService->registerUser($form);

                $this->addFlash('success', 'Регистрация успешна! Добро пожаловать!');

                return $userAuthenticator->authenticateUser(
                    $user,
                    $authenticator,
                    $request
                );
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }catch (\Exception $exception) {
                $this->addFlash('error', "Не удалось зарегестрировать пользоавтеля");
                return $this->redirectToRoute('user_register');
            }
        }

        return $this->render('user/register.html.twig', [
            'form' => $form->createView(),
        ]);

    }

    #[Route('/logout', name: 'user_logout')]
    public function logout(Request $request): void
    {
    }

    #[Route('/login', name: 'user_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError() ? "Неверный логин или пароль" : null;

        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('user/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

}
