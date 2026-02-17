<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Guest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class GuestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'attr' => [
                    'placeholder' => 'Vor- und Nachname',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-Mail (optional)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'email@beispiel.de',
                ],
                'help' => 'Wird benötigt, falls der Gast später zur App eingeladen werden soll.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Guest::class,
        ]);
    }
}
