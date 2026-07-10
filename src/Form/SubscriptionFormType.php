<?php

namespace App\Form;

use App\Entity\Subscription;
use App\Enum\BillingPeriodType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
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
                    new Assert\NotBlank(['message' => 'Название сервиса обязательно']),
                    new Assert\Length(['max' => 255, 'maxMessage' => 'Не более 255 сиволов']),
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
                    new Assert\NotBlank(['message' => 'Введите стоимость']),
                    new Assert\PositiveOrZero(['message' => 'Стоимость должна быть положительной или равной нулю']),
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
                    'Белорусский рубль' => 'BYR',
                    'Тенге' => 'KZT'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Обязательно выберите валюту']),
                ]
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'Дата начала',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'default' => (new \DateTime())->format('Y-m-d'),
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
                    'id' => 'billingPeriodSelect',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Выберите период оплаты'])
                ]
            ])
            ->add('customBillingPeriod', IntegerType::class, [
                'label' => 'Колличество дней',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Например: 14',
                    'min' => 1,
                    'max' => 365,
                    'id' => 'customPeriodValue'
                ]
            ]);


        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (isset($data['billingPeriod']) && $data['billingPeriod'] === BillingPeriodType::CUSTOM->value) {
                $form->add('customBillingPeriod', IntegerType::class, [
                    'label' => 'Колличество дней',
                    'required' => true,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Например: 14',
                        'min' => 1,
                        'max' => 365,
                        'id' => 'customPeriodValue'
                    ],
                    'constraints' => [
                        new Assert\NotBlank(['message' => 'Укажите количество дней']),
                        new Assert\Positive(['message' => 'Введите положительное число']),
                        new Assert\LessThanOrEqual([
                            'value' => 365,
                            'message' => 'Количество дней не может превышать 365',
                        ]),
                    ],
                ]);
            } else {
                $form->add('customBillingPeriod', IntegerType::class, [
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Например 14',
                        'min' => 1,
                        'max' => 365,
                        'id' => 'customPeriodValue'
                    ]
                ]);
            }
        });
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
