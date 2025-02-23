<?php

declare(strict_types = 1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuestController extends AbstractController
{
    #[Route('/quests', name: 'app_quests')]
    public function index(): Response
    {
        return $this->render('quest/index.html.twig');
    }
}
