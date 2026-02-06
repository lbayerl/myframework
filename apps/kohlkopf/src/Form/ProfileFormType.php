<?php

declare(strict_types=1);

namespace App\Form;

use MyFramework\Core\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', TextType::class, [
                'label' => 'Anzeigename',
                'required' => false,
                'constraints' => [
                    new Assert\Length(max: 100),
                ],
                'help' => 'Wird angezeigt statt deiner E-Mail-Adresse',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. Max',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
