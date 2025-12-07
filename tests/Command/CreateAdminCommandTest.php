<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CreateAdminCommandTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    public function testCreateAdminCommandSucceeds(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('app:create-admin');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['admin@test.com', 'password123']);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Utilisateur administrateur "admin@test.com" créé avec succès', $output);
        $this->assertSame(0, $commandTester->getStatusCode());

        $user = UserFactory::repository()->findOneBy(['email' => 'admin@test.com']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('admin@test.com', $user->getEmail());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testCreateAdminCommandFailsWithEmptyEmail(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('app:create-admin');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['', 'password123']);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('L\'email ne peut pas être vide', $output);
        $this->assertSame(1, $commandTester->getStatusCode());
    }

    public function testCreateAdminCommandFailsWithDuplicateEmail(): void
    {
        UserFactory::createOne([
            'email' => 'existing@test.com',
            'plainPassword' => 'password123',
            'roles' => ['ROLE_ADMIN'],
        ]);

        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('app:create-admin');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['existing@test.com', 'password123']);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Un utilisateur avec l\'email "existing@test.com" existe déjà', $output);
        $this->assertSame(1, $commandTester->getStatusCode());
    }

    public function testCreateAdminCommandFailsWithEmptyPassword(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('app:create-admin');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['admin@test.com', '']);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Le mot de passe ne peut pas être vide', $output);
        $this->assertSame(1, $commandTester->getStatusCode());
    }
}
