<?php

declare(strict_types=1);

namespace App\Tests\Trait;

use App\Entity\User;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Persistence\Proxy;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait LoginTrait
{
    use Factories;

    private function loginAsAdmin(
        KernelBrowser $client,
        string $email = 'admin@wowplanet.com',
        string $password = 'password123'
    ): void {
        /** @var User $admin */
        $admin = UserFactory::createOne([
            'email' => $email,
            'plainPassword' => $password,
            'roles' => ['ROLE_ADMIN'],
        ]);

        $client->loginUser($admin, 'main');
    }

    private function createAdmin(string $email = 'admin@wowplanet.com', string $password = 'password123'): User
    {
        $admin = UserFactory::createOne([
            'email' => $email,
            'plainPassword' => $password,
            'roles' => ['ROLE_ADMIN'],
        ]);

        if ($admin instanceof Proxy) {
            /** @var User */
            return $admin->_real();
        }

        /** @var User $admin */
        return $admin;
    }
}
