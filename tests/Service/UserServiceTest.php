<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserServiceTest extends TestCase
{
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private ValidatorInterface $validator;
    private UserService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->service = new UserService(
            $this->userRepository,
            $this->passwordHasher,
            $this->validator
        );
    }

    private function createUserMock(string $email = 'test@example.com'): User
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn($email);
        return $user;
    }

    private function createFormMock(User $user, string $plainPassword): FormInterface
    {
        $passwordField = $this->createMock(FormInterface::class);
        $passwordField->method('getData')->willReturn($plainPassword);

        $form = $this->createMock(FormInterface::class);
        $form->method('getData')->willReturn($user);
        $form->method('get')->with('plainPassword')->willReturn($passwordField);

        return $form;
    }

    public function testRegisterUserCreatesUserSuccessfully(): void
    {
        // Arrange
        $user = $this->createUserMock('new@example.com');
        $plainPassword = 'secret123';
        $hashedPassword = '$2y$13$hashedvalue';

        $form = $this->createFormMock($user, $plainPassword);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'new@example.com'])
            ->willReturn(null);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, $plainPassword)
            ->willReturn($hashedPassword);

        $user->expects($this->once())->method('setPassword')->with($hashedPassword);
        $user->expects($this->once())->method('setRoles')->with(['ROLE_USER']);

        $violations = new ConstraintViolationList([]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($user)
            ->willReturn($violations);

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($user);

        // Act
        $result = $this->service->registerUser($form);

        // Assert
        $this->assertSame($user, $result);
    }

    public function testRegisterUserThrowsExceptionWhenEmailAlreadyExists(): void
    {
        // Arrange
        $existingUser = $this->createUserMock('existing@example.com');
        $form = $this->createFormMock($existingUser, 'password123');

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'existing@example.com'])
            ->willReturn($existingUser);

        $this->passwordHasher->expects($this->never())->method('hashPassword');
        $this->validator->expects($this->never())->method('validate');
        $this->userRepository->expects($this->never())->method('save');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Пользователь с таким email уже существует');

        $this->service->registerUser($form);
    }

    public function testRegisterUserThrowsExceptionWhenValidationFails(): void
    {
        // Arrange
        $user = $this->createUserMock('invalid@example.com');
        $form = $this->createFormMock($user, 'short');

        $this->userRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->passwordHasher
            ->method('hashPassword')
            ->willReturn('$2y$13$hashed');

        $user->method('setPassword');
        $user->method('setRoles');

        $violation1 = $this->createMock(ConstraintViolationInterface::class);
        $violation1->method('getMessage')->willReturn('Email слишком короткий');

        $violation2 = $this->createMock(ConstraintViolationInterface::class);
        $violation2->method('getMessage')->willReturn('Пароль слишком простой');

        // Используем реальный класс вместо мока интерфейса
        $violations = new ConstraintViolationList([$violation1, $violation2]);

        $this->validator
            ->method('validate')
            ->willReturn($violations);

        $this->userRepository->expects($this->never())->method('save');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email слишком короткий, Пароль слишком простой');

        $this->service->registerUser($form);
    }

    public function testRegisterUserHashesPasswordCorrectly(): void
    {
        // Arrange
        $user = $this->createUserMock('user@example.com');
        $plainPassword = 'mySecretPassword!';
        $expectedHash = '$2y$13$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy';

        $form = $this->createFormMock($user, $plainPassword);

        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, $plainPassword)
            ->willReturn($expectedHash);

        $user->expects($this->once())->method('setPassword')->with($expectedHash);

        $violations = new ConstraintViolationList([]);
        $this->validator->method('validate')->willReturn($violations);

        $this->userRepository->method('save');

        // Act
        $this->service->registerUser($form);

        // Assert
        // setPassword вызван с хешированным паролем
    }

    public function testRegisterUserSetsDefaultRole(): void
    {
        // Arrange
        $user = $this->createUserMock('user@example.com');
        $form = $this->createFormMock($user, 'password');

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('$hash');

        $user->method('setPassword');
        $user->expects($this->once())->method('setRoles')->with(['ROLE_USER']);

        $violations = new ConstraintViolationList([]);
        $this->validator->method('validate')->willReturn($violations);

        $this->userRepository->method('save');

        // Act
        $this->service->registerUser($form);

        // Assert
        // Роль ROLE_USER установлена
    }

    public function testRegisterUserReturnsCreatedUser(): void
    {
        // Arrange
        $user = $this->createUserMock('return@example.com');
        $form = $this->createFormMock($user, 'password');

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('$hash');
        $user->method('setPassword');
        $user->method('setRoles');

        $violations = new ConstraintViolationList([]);
        $this->validator->method('validate')->willReturn($violations);
        $this->userRepository->method('save');

        // Act
        $result = $this->service->registerUser($form);

        // Assert
        $this->assertSame($user, $result);
    }

    public function testRegisterUserDoesNotSaveWhenValidationFails(): void
    {
        // Arrange
        $user = $this->createUserMock('fail@example.com');
        $form = $this->createFormMock($user, 'password');

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('$hash');
        $user->method('setPassword');
        $user->method('setRoles');

        $violation = $this->createMock(ConstraintViolationInterface::class);
        $violation->method('getMessage')->willReturn('Ошибка валидации');

        // Используем реальный класс вместо мока интерфейса
        $violations = new ConstraintViolationList([$violation]);

        $this->validator->method('validate')->willReturn($violations);

        $this->userRepository
            ->expects($this->never())
            ->method('save');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);

        $this->service->registerUser($form);
    }
}
