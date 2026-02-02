<?php

declare(strict_types=1);

namespace MyFramework\Core\Security\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'E-Mail',
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Email(),
            ],
        ]);
    }
}
