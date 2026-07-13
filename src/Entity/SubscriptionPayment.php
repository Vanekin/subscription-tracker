<?php

namespace App\Entity;

use App\Repository\SubscriptionPaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionPaymentRepository::class)]
class SubscriptionPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Subscription::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Subscription $subscription = null;

    #[ORM\Column]
    private ?\DateTime $paidAt = null;

    // Сумма и валюта на момент оплаты (копия из подписки - чтобы история
    // не "поехала", если пользователь потом изменит стоимость подписки)
    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\Column(length: 10)]
    private ?string $currency = null;

    // Сумма, пересчитанная в рубли по курсу ЦБ РФ на момент оплаты
    #[ORM\Column]
    private ?float $amountInRub = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): static
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function getPaidAt(): ?\DateTime
    {
        return $this->paidAt;
    }

    public function setPaidAt(\DateTime $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getAmountInRub(): ?float
    {
        return $this->amountInRub;
    }

    public function setAmountInRub(float $amountInRub): static
    {
        $this->amountInRub = $amountInRub;

        return $this;
    }
}
