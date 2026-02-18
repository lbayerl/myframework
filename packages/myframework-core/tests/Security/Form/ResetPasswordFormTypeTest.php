<?php

declare(strict_types=1);

namespace MyFramework\Core\Tests\Security\Form;

use MyFramework\Core\Security\Form\ResetPasswordFormType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

final class ResetPasswordFormTypeTest extends TestCase
{
    public function testPasswordFieldUsesWeakStrengthConstraint(): void
    {
        $formType = new ResetPasswordFormType();
        $builder = $this->createMock(FormBuilderInterface::class);

        $addedFields = [];
        $builder->method('add')->willReturnCallback(function (string $name, string $type, array $options = []) use ($builder, &$addedFields) {
            $addedFields[$name] = ['type' => $type, 'options' => $options];
            return $builder;
        });

        $formType->buildForm($builder, []);

        self::assertArrayHasKey('plainPassword', $addedFields);
        self::assertSame(RepeatedType::class, $addedFields['plainPassword']['type']);

        $constraints = $addedFields['plainPassword']['options']['constraints'];
        self::assertCount(4, $constraints);

        // Verify PasswordStrength uses STRENGTH_WEAK (score 1), not STRENGTH_MEDIUM (score 2)
        $strengthConstraint = $constraints[2];
        self::assertInstanceOf(PasswordStrength::class, $strengthConstraint);
        self::assertSame(PasswordStrength::STRENGTH_WEAK, $strengthConstraint->minScore);

        // Verify NotCompromisedPassword has skipOnError enabled
        $compromisedConstraint = $constraints[3];
        self::assertInstanceOf(NotCompromisedPassword::class, $compromisedConstraint);
        self::assertTrue($compromisedConstraint->skipOnError);
    }
}
