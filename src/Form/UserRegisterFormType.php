<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Validator\Constraints as Assert;

class UserRegisterFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Имя пользоавтеля',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Введите имя',
                    'class' => 'form-control',
                    'autofocus' => true,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Имя пользователя обязательно']),
                    new Assert\Length(['min' => 2, 'minMessage' => 'Имя не может быть короче 3 символов']),
                ]
            ])
            ->add('surname', TextType::class, [
                'label' => 'Фамилия пользоавтеля',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Введите фамилию',
                    'class' => 'form-control',
                    'autofocus' => true,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Фамилия  обязательна']),
                    new Assert\Length(['min' => 2, 'minMessage' => 'Фамилия не может быть короче 3 символов']),
                ]
            ])
            ->add('patronymic', TextType::class, [
                'label' => 'Отчество',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Введите отчество (необязательно)',
                    'class' => 'form-control',
                ],
                'constraints' => [
                        new Assert\Length([
                        'min' => 3, 'minMessage' => 'Отчество не может быть короче {{ limit }} символов'
                    ]),
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => [
                    'placeholder' => 'example@mail.com',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Email обязателен']),
                    new Assert\Email(['message' => "Введите корректный email"])
                ]
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => true,
                'invalid_message' => 'Пароли не совпадают',
                'first_options' => [
                    'label' => 'Пароль',
                    'attr' => [
                        'placeholder' => 'Введите пароль',
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                    ],
                    'constraints' => [
                        new Assert\NotBlank(['message' => 'Пароль обязателен']),
                        new Assert\Length(['min' => 6, 'minMessage' => 'Пароль должен содержать минимум {{ limit }} символов']),
                        new Assert\Regex([
                            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                            'message' => 'Пароль должен содержать хотя бы одну строчную букву, заглавную букву и одну цифру',
                        ])
                    ],
                ],
                'second_options' => [
                    'label' => 'Подтвердите пароль',
                    'attr' => [
                        'placeholder' => 'Подтвердите пароль',
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                    ],
                    'constraints' => [
                        new Assert\NotBlank(['message' => 'Подтверждение парорля обязательно'])
                    ],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'registration_form',
            'allow_extra_fields' => false,
            'validation_groups' => ['Default']
        ]);
    }
}
