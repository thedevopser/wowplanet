<?php

declare(strict_types = 1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/characters')]
class CharactersController extends AbstractController
{
    #[Route('/all', name: 'app_characters_all')]
    public function index(): Response
    {
        return $this->render('characters/index.html.twig');
    }

    #[Route('/search', name: 'app_characters_search')]
    public function search(): Response
    {
        return $this->render('characters/index.html.twig');
    }
}
