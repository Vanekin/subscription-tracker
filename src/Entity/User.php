<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;

#[UniqueEntity(fields: ['email'], message: 'Пользователь с таким email уже зарегестрирован')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements PasswordAuthenticatedUserInterface, UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Обязательно введите email')]
    #[Assert\Email(message: 'веденный вами email не корректный')]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(min: 6, minMessage: 'Минимальная длина пароля {{ limit }} символов')]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Введите имя')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Имя не можеь быть короче 3 символов', maxMessage: 'Имя слишком длинное')]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Введите фамилию')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Фамилия не может быть короче 3 символов', maxMessage: 'Фамилия слишком длинное')]
    private ?string $surname = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Отчество не может быть короче 3 символов', maxMessage: 'Отчество слишком длинное')]
    private ?string $patronymic = null;

    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $subscriptions;


    #[ORM\Column(type: 'json')]
    private array $roles;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function setSubscriptions(Collection $subscriptions): void
    {
        $this->subscriptions = $subscriptions;
    }

    public function getPatronymic(): ?string
    {
        return $this->patronymic;
    }

    public function setPatronymic(?string $patronymic): void
    {
        $this->patronymic = $patronymic;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setSurname(?string $surname): void
    {
        $this->surname = $surname;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function eraseCredentials(): void
    {

    }

    public function getUserIdentifier(): string {
        return $this->email;
    }

}
