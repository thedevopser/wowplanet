<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CharacterControllerTest extends WebTestCase
{
    public function testCharactersPageRedirectsToOAuthWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        $this->assertResponseRedirects('/oauth');
    }

    public function testCharactersPageStoresRedirectRouteInSession(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        $this->assertResponseRedirects('/oauth');
    }

    public function testCharactersPageIsAccessibleWhenAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('blizzard_access_token', 'fake_token');
        $session->set('blizzard_token_expires_at', time() + 3600);
        $session->save();

        $client->request('GET', '/characters');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mes Personnages');
    }

    public function testCharactersPageShowsSubmitButton(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('blizzard_access_token', 'fake_token');
        $session->set('blizzard_token_expires_at', time() + 3600);
        $session->save();

        $crawler = $client->request('GET', '/characters');

        $this->assertCount(1, $crawler->filter('button[type="submit"]'));
    }

    public function testCharactersProcessRedirectsToOAuthWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('POST', '/characters/process');

        $this->assertResponseRedirects('/oauth');
    }

    public function testCharactersResultsRedirectsWhenNoData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters/results');

        $this->assertResponseRedirects('/characters');
    }

    public function testCharactersResultsDisplaysDataFromSession(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('characters_results', [
            [
                'name' => 'TestCharacter',
                'level' => 70,
                'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                'faction' => ['name' => 'Alliance', 'type' => 'ALLIANCE'],
            ],
        ]);
        $session->save();

        $client->request('GET', '/characters/results');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mes Personnages');
    }

    public function testCharactersResultsClearsSessionAfterDisplay(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('characters_results', [
            [
                'name' => 'TestCharacter',
                'level' => 70,
                'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                'faction' => ['name' => 'Alliance', 'type' => 'ALLIANCE'],
            ],
        ]);
        $session->save();

        $client->request('GET', '/characters/results');
        $this->assertResponseIsSuccessful();

        $this->assertNull($client->getRequest()->getSession()->get('characters_results'));
    }

    public function testCharactersResultsDisplaysEmptyMessage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('characters_results', []);
        $session->save();

        $client->request('GET', '/characters/results');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.text-xl', 'Aucun personnage trouve');
    }

    public function testCharactersPageRedirectsWhenTokenExpired(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('blizzard_access_token', 'fake_token');
        $session->set('blizzard_token_expires_at', time() - 3600);
        $session->save();

        $client->request('GET', '/characters');

        $this->assertResponseRedirects('/oauth');
    }

    public function testCharactersResultsDisplaysCharacterCount(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('characters_results', [
            [
                'name' => 'Char1',
                'level' => 70,
                'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                'faction' => ['name' => 'Alliance', 'type' => 'ALLIANCE'],
            ],
            [
                'name' => 'Char2',
                'level' => 60,
                'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                'playable_class' => ['id' => 1, 'name' => 'Warrior'],
                'faction' => ['name' => 'Horde', 'type' => 'HORDE'],
            ],
        ]);
        $session->save();

        $crawler = $client->request('GET', '/characters/results');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.font-bold.text-white', '2');
    }

    public function testCharactersResultsDisplaysWithGuild(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('characters_results', [
            [
                'name' => 'TestCharacter',
                'level' => 70,
                'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                'faction' => ['name' => 'Alliance', 'type' => 'ALLIANCE'],
                'guild' => ['name' => 'Test Guild'],
            ],
        ]);
        $session->save();

        $crawler = $client->request('GET', '/characters/results');

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Test Guild', $content);
    }

    public function testCharactersResultsDisplaysWithoutGuild(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('characters_results', [
            [
                'name' => 'TestCharacter',
                'level' => 70,
                'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                'faction' => ['name' => 'Alliance', 'type' => 'ALLIANCE'],
            ],
        ]);
        $session->save();

        $crawler = $client->request('GET', '/characters/results');

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Sans guilde', $content);
    }

    public function testCharactersProcessRedirectsWhenTokenExpired(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('blizzard_access_token', 'fake_token');
        $session->set('blizzard_token_expires_at', time() - 3600);
        $session->save();

        $client->request('POST', '/characters/process');

        $this->assertResponseRedirects('/oauth');
    }
}
