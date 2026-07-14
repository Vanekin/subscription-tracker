<?php

namespace App\Form;

use App\Entity\Subscription;
use App\Enum\BillingPeriodType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SubscriptionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Название сервиса',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Введите название сервиса',
                    'class' => 'form-control',
                    'autofocus' => true,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Название сервиса обязательно'),
                    new Assert\Length(max: 255, maxMessage: 'Не более 255 сиволов'),
                ]])
            ->add('amount', NumberType::class, [
                'label' => 'Стоимость подписки',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Введите стоимость',
                    'class' => 'form-control',
                    'autofocus' => true,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Введите стоимость'),
                    new Assert\PositiveOrZero(message: 'Стоимость должна быть положительной или равной нулю'),
                ]
            ])
            ->add('currency', ChoiceType::class, [
                'label' => 'Валюта',
                'required' => true,
                'placeholder' => 'Выберите валюту',
                'choices' => [
                    'Доллар США' => 'USD',
                    'Евро' => 'EUR',
                    'Рубли' => 'RUB',
                    'Фунт Стерлингов' => 'GBP',
                    'Йены' => 'JPY',
                    'Юань' => 'CNY',
                    'Белорусский рубль' => 'BYN',
                    'Тенге' => 'KZT'
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Обязательно выберите валюту'),
                ]
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Дата начала',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'empty_data' => (new \DateTime())->format('Y-m-d'),
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('billingPeriod', ChoiceType::class, [
                'label' => 'Период оплаты',
                'choices' => [
                    'Ежедневно' => BillingPeriodType::DAILY->value,
                    'Еженедельно' => BillingPeriodType::WEEKLY->value,
                    'Ежемесячно' => BillingPeriodType::MONTHLY->value,
                    'Ежеквартально' => BillingPeriodType::QUARTERLY->value,
                    'Раз в полгода' => BillingPeriodType::SEMIANNUALLY->value,
                    'Ежегодно' => BillingPeriodType::YEARLY->value,
                    'Свой период' => BillingPeriodType::CUSTOM->value,
                ],
                'placeholder' => 'Выбирите период оплаты',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Выберите период оплаты')
                ]
            ])
            ->add('customPeriodDays', IntegerType::class, [
                'label' => 'Свой период (в днях)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Введите количество дней',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\Positive(message: 'Количество дней должно быть больше нуля'),
                ]
            ])
            ->add('preferredDay', IntegerType::class, [
                'label' => 'День списания (число месяца)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Например, 15',
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 31,
                ],
                'constraints' => [
                    new Assert\Range(notInRangeMessage: 'Число месяца должно быть от {{ min }} до {{ max }}', min: 1, max: 31),
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Subscription::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'subscriptionForm',
            'allow_extra_fields' => false,
        ]);
    }


}
