<?php

declare(strict_types=1);

namespace MyFramework\Core\Security\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'first_options' => ['label' => 'Neues Passwort'],
            'second_options' => ['label' => 'Neues Passwort wiederholen'],
            'invalid_message' => 'Die Passwörter müssen übereinstimmen.',
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Length(min: 8, max: 4096),
            ],
        ]);
    }
}
