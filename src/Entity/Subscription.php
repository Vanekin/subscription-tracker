<?php

namespace App\Entity;

use App\Enum\BillingPeriodType;
use App\Repository\SubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    private ?float $amount = null;

    #[ORM\Column(length: 10)]
    private ?string $currency = null;

    #[ORM\Column]
    private ?\DateTime $startDate = null;

    #[ORM\Column]
    private ?\DateTime $nextPaymentDate = null;

    #[ORM\Column(length: 255)]
    private ?string $billingPeriod = null;

    #[ORM\Column(nullable: true)]
    private ?int $customPeriodDays = null;

    #[ORM\Column(nullable: true)]
    private ?int $preferredDay = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $reminderSentFor = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'subscriptions')]
    private ?User $user = null;

    /**
     * @var Collection<int, SubscriptionPayment>
     */
    #[ORM\OneToMany(targetEntity: SubscriptionPayment::class, mappedBy: 'subscription', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $payments;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): static
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

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function setStartDate(\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getNextPaymentDate(): ?\DateTime
    {
        return $this->nextPaymentDate;
    }

    public function setNextPaymentDate(\DateTime $nextPaymentDate): static
    {
        $this->nextPaymentDate = $nextPaymentDate;

        return $this;
    }

    public function getBillingPeriod(): ?string
    {
        return $this->billingPeriod;
    }

    public function setBillingPeriod(string $billingPeriod): static
    {
        $this->billingPeriod = $billingPeriod;

        return $this;
    }

    public function getCustomPeriodDays(): ?int
    {
        return $this->customPeriodDays;
    }

    public function setCustomPeriodDays(?int $customPeriodDays): static
    {
        $this->customPeriodDays = $customPeriodDays;

        return $this;
    }

    public function getPreferredDay(): ?int
    {
        return $this->preferredDay;
    }

    public function setPreferredDay(?int $preferredDay): static
    {
        $this->preferredDay = $preferredDay;

        return $this;
    }

    public function getReminderSentFor(): ?\DateTime
    {
        return $this->reminderSentFor;
    }

    public function setReminderSentFor(?\DateTime $reminderSentFor): static
    {
        $this->reminderSentFor = $reminderSentFor;

        return $this;
    }

    public function getCancelledAt(): ?\DateTime
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTime $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->cancelledAt === null;
    }

    /**
     * @return Collection<int, SubscriptionPayment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(SubscriptionPayment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setSubscription($this);
        }

        return $this;
    }

    public function getBillingPeriodLabel(): string
    {
        if (!$this->billingPeriod) {
            return '-';
        }

        return BillingPeriodType::from($this->billingPeriod)->getName();
    }
}
