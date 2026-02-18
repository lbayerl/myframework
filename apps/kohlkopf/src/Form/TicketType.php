<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Ticket;
use App\Enum\TicketType as TicketTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class TicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<int, array{id: string, name: string}> $attendeeChoices */
        $attendeeChoices = $options['attendee_choices'];

        // Convert to name => id format for choice field
        $choices = [];
        foreach ($attendeeChoices as $attendee) {
            $choices[$attendee['name']] = $attendee['id'];
        }

        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Tickettyp',
                'choices' => [
                    '🎫 Hard Ticket (physisch)' => TicketTypeEnum::HARD_TICKET,
                    '📧 E-Ticket (PDF)' => TicketTypeEnum::E_TICKET,
                    '📱 App-Ticket' => TicketTypeEnum::APP_TICKET,
                ],
                'expanded' => true,
                'multiple' => false,
                'attr' => ['class' => 'ticket-type-selector'],
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Preis',
                'currency' => 'EUR',
                'required' => false,
                'attr' => [
                    'placeholder' => '0,00',
                    'inputmode' => 'decimal',
                ],
                'constraints' => [
                    new Assert\PositiveOrZero(message: 'Preis muss positiv sein.'),
                ],
            ])
            ->add('ownerId', ChoiceType::class, [
                'label' => 'Ticket gehört',
                'choices' => $choices,
                'mapped' => false,
                'placeholder' => '(noch nicht zugeordnet)',
                'required' => false,
                'help' => 'Wer wird mit diesem Ticket zum Konzert gehen?',
            ])
            ->add('purchaserId', ChoiceType::class, [
                'label' => 'Gekauft von',
                'choices' => $choices,
                'mapped' => false,
                'placeholder' => '(noch nicht zugeordnet)',
                'required' => false,
                'help' => 'Wer hat dieses Ticket bezahlt?',
            ])
            ->add('seat', null, [
                'label' => 'Sitzplatz (optional)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'z.B. Block A, Reihe 5, Platz 12',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ticket::class,
            'attendee_choices' => [],
        ]);

        $resolver->setAllowedTypes('attendee_choices', 'array');
    }
}
