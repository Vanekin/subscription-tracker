<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
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
                    new Assert\Positive(['message' => 'тоимость не может быть отрицательной']),
                ]
            ])
            ->add('currency', TextType::class, [
                'label' => 'Валюта',
                'required' => true,
                'attr' => [
                    'placeholder' => ''
                ]
            ]);
    }


}
