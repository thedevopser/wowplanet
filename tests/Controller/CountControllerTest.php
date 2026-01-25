<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CountControllerTest extends WebTestCase
{
    public function testCountPageRedirectsToOAuthWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/count');

        $this->assertResponseRedirects('/oauth');
    }

    public function testCountPageStoresRedirectRouteInSession(): void
    {
        $client = static::createClient();
        $client->request('GET', '/count');

        /** @var SessionInterface $session */
        $session = $client->getContainer()->get('session.factory')->createSession();
        $client->getRequest()->getSession();

        $this->assertResponseRedirects('/oauth');
    }

    public function testCountPageIsAccessibleWhenAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/count');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('blizzard_access_token', 'fake_token');
        $session->set('blizzard_token_expires_at', time() + 3600);
        $session->save();

        $client->request('GET', '/count');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Compteur de Personnages');
    }

    public function testCountPageShowsSubmitButton(): void
    {
        $client = static::createClient();
        $client->request('GET', '/count');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('blizzard_access_token', 'fake_token');
        $session->set('blizzard_token_expires_at', time() + 3600);
        $session->save();

        $crawler = $client->request('GET', '/count');

        $this->assertCount(1, $crawler->filter('button[type="submit"]'));
    }

    public function testCountProcessRedirectsToOAuthWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('POST', '/count/process');

        $this->assertResponseRedirects('/oauth');
    }

    public function testCountResultsRedirectsWhenNoData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/count/results');

        $this->assertResponseRedirects('/count');
    }

    public function testCountResultsDisplaysDataFromSession(): void
    {
        $client = static::createClient();
        $client->request('GET', '/count');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('count_results', [
            'classes' => [
                [
                    'name' => 'Paladin',
                    'id' => 2,
                    'count' => 5,
                    'characters' => [],
                ],
            ],
            'total_characters' => 5,
        ]);
        $session->save();

        $client->request('GET', '/count/results');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Compteur de Personnages');
    }

    public function testCountResultsClearsSessionAfterDisplay(): void
    {
        $client = static::createClient();
        $client->request('GET', '/count');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('count_results', [
            'classes' => [],
            'total_characters' => 0,
        ]);
        $session->save();

        $client->request('GET', '/count/results');
        $this->assertResponseIsSuccessful();

        $this->assertNull($client->getRequest()->getSession()->get('count_results'));
    }

    public function testCountResultsDisplaysErrorMessage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/count');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('count_results', [
            'classes' => [],
            'total_characters' => 0,
            'error' => 'Aucun compte WoW trouve',
        ]);
        $session->save();

        $client->request('GET', '/count/results');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.mt-2', 'Aucun compte WoW trouve');
    }

    public function testCountResultsDisplaysEmptyMessage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/count');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('count_results', [
            'classes' => [],
            'total_characters' => 0,
        ]);
        $session->save();

        $client->request('GET', '/count/results');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.text-xl', 'Aucun personnage trouve');
    }

    public function testCountPageRedirectsWhenTokenExpired(): void
    {
        $client = static::createClient();
        $client->request('GET', '/count');

        /** @var SessionInterface $session */
        $session = $client->getRequest()->getSession();
        $session->set('blizzard_access_token', 'fake_token');
        $session->set('blizzard_token_expires_at', time() - 3600);
        $session->save();

        $client->request('GET', '/count');

        $this->assertResponseRedirects('/oauth');
    }
}
