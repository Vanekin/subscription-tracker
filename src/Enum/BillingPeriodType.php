<?php

namespace App\Enum;

enum BillingPeriodType: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case SEMIANNUALLY = 'semiannually';
    case YEARLY = 'yearly';
    case CUSTOM = 'custom';

    public function getName(): string
    {
        return match ($this) {
            self::DAILY => 'Ежедневно',
            self::WEEKLY => 'Еженедельно',
            self::MONTHLY => 'Ежемесячно',
            self::QUARTERLY => 'Раз в квартал',
            self::SEMIANNUALLY => 'Раз в пол года',
            self::YEARLY => 'Ежегодно',
            self::CUSTOM => 'Свой период',
        };
    }

    public function getDays(): ?int
    {
        return match ($this) {
            self::DAILY => 1,
            self::WEEKLY => 7,
            self::MONTHLY => 30,
            self::QUARTERLY => 90,
            self::SEMIANNUALLY => 180,
            self::YEARLY => 365,
            self::CUSTOM => null,
        };
    }

    public function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $choice) {
            $choices[$choice->getName()] = $choice->value;
        }
        return $choices;
    }

    public function isCustom(): bool
    {
        return $this === self::CUSTOM;
    }

    public function getByName(string $name): BillingPeriodType
    {
        foreach (self::cases() as $choice) {
            if ($choice->getName() === $name) {
                return $choice;
            }
        }
        return BillingPeriodType::CUSTOM;
    }
}
