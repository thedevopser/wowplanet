<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un nouvel utilisateur administrateur',
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Création d\'un utilisateur administrateur');

        $email = $io->ask('Email de l\'administrateur');

        if (!is_string($email) || $email === '') {
            $io->error('L\'email ne peut pas être vide');

            return Command::FAILURE;
        }

        $existingUser = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existingUser !== null) {
            $io->error(sprintf('Un utilisateur avec l\'email "%s" existe déjà', $email));

            return Command::FAILURE;
        }

        $question = new Question('Mot de passe');
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        $password = $io->askQuestion($question);

        if (!is_string($password) || $password === '') {
            $io->error('Le mot de passe ne peut pas être vide');

            return Command::FAILURE;
        }

        $hashedPassword = $this->passwordHasher->hashPassword(
            new User('dummy@example.com', 'dummy', []),
            $password
        );

        $user = new User($email, $hashedPassword, ['ROLE_ADMIN']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Utilisateur administrateur "%s" créé avec succès !', $email));

        return Command::SUCCESS;
    }
}
