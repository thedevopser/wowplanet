<?php

declare(strict_types = 1);

namespace App\Controller;

use App\Services\BattleNetProfileDataApi;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/characters')]
class CharactersController extends AbstractController
{
    public function __construct(
        private readonly BattleNetProfileDataApi $profileDataApi,
        private readonly PaginatorInterface $paginator,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/all', name: 'app_characters_all')]
    public function getAllCharacters(Request $request): Response|RedirectResponse
    {
        if (!$this->profileDataApi->isAuthenticated()) {
            return $this->redirectToRoute('blizzard_auth', [
                'redirect' => $request->getRequestUri(),
            ]);
        }

        $characters = $this->profileDataApi->getFormattedUserCharacters();

        // Tri par ilvl décroissant
        usort($characters, function ($a, $b) {
            return ($b['ilvl'] ?? 0) <=> ($a['ilvl'] ?? 0); // Déjà dans le bon ordre
        });

        $pagination = $this->paginator->paginate(
            $characters,
            $request->query->getInt('page', 1),
            9
        );

        return $this->render('characters/all.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/detail/{realm}/{character}', name: 'app_characters_detail')]
    public function detail(string $realm, string $character): Response
    {
        return new Response('Détail du personnage ' . $character . ' sur le royaume ' . $realm);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/search', name: 'app_characters_search')]
    public function search(Request $request): Response|RedirectResponse
    {
        if (!$this->profileDataApi->isAuthenticated()) {
            return $this->redirectToRoute('blizzard_auth', [
                'redirect' => $request->getRequestUri(),
            ]);
        }

        $characters = $this->profileDataApi->getUserCharacters();

        return $this->render('characters/search.html.twig', [
            'characters' => $characters,
        ]);
    }
}
