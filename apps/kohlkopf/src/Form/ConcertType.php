<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Concert;
use App\Enum\ConcertStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ConcertType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // UI: „Wer?"
            ->add('title', TextType::class, [
                'label' => 'Wer?',
                'attr' => [
                    'placeholder' => 'Künstler/Band',
                    'autocomplete' => 'off',
                    'inputmode' => 'text',
                ],
            ])
            // UI: „Wann?" — getrennt als Datum und Uhrzeit
            ->add('date', DateType::class, [
                'label' => 'Datum',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'string',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'off',
                    'min' => (new \DateTime())->format('Y-m-d'),
                ],
            ])
            ->add('time', TimeType::class, [
                'label' => 'Uhrzeit',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'string',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'off',
                    'step' => 60, // 1-Minuten-Schritte
                ],
            ])
            // UI: „Wo?"
            ->add('whereText', TextType::class, [
                'label' => 'Wo?',
                'attr' => [
                    'placeholder' => 'Venue / Stadt',
                    'autocomplete' => 'on',
                    'inputmode' => 'text',
                ],
            ])
            // UI: „Kommentar (optional)"
            ->add('comment', TextareaType::class, [
                'label' => 'Kommentar (optional)',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'z.B. Treffpunkt, Tickets, Hinweise …',
                ],
            ])
            // UI: „Link (optional)"
            ->add('externalLink', UrlType::class, [
                'label' => 'Link (optional)',
                'required' => false,
                'attr' => [
                    'inputmode' => 'url',
                    'placeholder' => 'https://…',
                ],
            ])
            // UI: „Status"
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Veröffentlicht' => ConcertStatus::PUBLISHED,
                    'Entwurf' => ConcertStatus::DRAFT,
                    'Abgesagt' => ConcertStatus::CANCELLED,
                ],
                'expanded' => false,
                'multiple' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Concert::class,
            'csrf_protection' => true,
        ]);
    }
}
