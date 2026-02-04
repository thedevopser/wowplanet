<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Trait\LoginTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class SecurityControllerTest extends WebTestCase
{
    use ResetDatabase;
    use Factories;
    use LoginTrait;

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Connexion');
    }

    public function testLoginFormIsDisplayed(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('input[name="_username"]'));
        $this->assertCount(1, $crawler->filter('input[name="_password"]'));
        $this->assertCount(1, $crawler->filter('button[type="submit"]'));
    }

    public function testAdminIsProtected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        $this->assertResponseRedirects('/login');
    }

    public function testDashboradIsVisibleWhenWeAreLog(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Dashboard Administration');

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString(
            'Cache Redis',
            $content
        );
    }
}
