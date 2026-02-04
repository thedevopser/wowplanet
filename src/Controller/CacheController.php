<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\RedisCacheInspectorService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/cache', name: 'admin_cache_')]
#[IsGranted('ROLE_ADMIN')]
final class CacheController extends AbstractController
{
    public function __construct(
        private readonly RedisCacheInspectorService $cacheInspector,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filter = $request->query->getString('filter', '*');

        if ($filter === '') {
            $filter = '*';
        }

        $this->logger->info('Cache inspector accessed', ['filter' => $filter]);

        $keys = $this->cacheInspector->listKeys($filter);
        $serverInfo = $this->cacheInspector->getServerInfo();

        return $this->render('admin/cache.html.twig', [
            'keys' => $keys,
            'serverInfo' => $serverInfo,
            'filter' => $filter === '*' ? '' : $filter,
        ]);
    }

    #[Route('/key', name: 'key_detail', methods: ['GET'])]
    public function keyDetail(Request $request): Response
    {
        $key = $request->query->getString('key');

        if ($key === '') {
            $this->addFlash('warning', 'Aucune cle specifiee.');

            return $this->redirectToRoute('admin_cache_index');
        }

        $value = $this->cacheInspector->getKeyValue($key);
        $ttl = $this->cacheInspector->getKeyTtl($key);
        $type = $this->cacheInspector->getKeyType($key);

        $decodedValue = null;

        if ($value !== null) {
            $decodedValue = $this->tryDecodeValue($value);
        }

        return $this->render('admin/cache_detail.html.twig', [
            'key' => $key,
            'rawValue' => $value,
            'decodedValue' => $decodedValue,
            'ttl' => $ttl,
            'type' => $type,
        ]);
    }

    #[Route('/delete', name: 'delete_key', methods: ['POST'])]
    public function deleteKey(Request $request): Response
    {
        $key = $request->request->getString('key');

        if ($key === '') {
            $this->addFlash('warning', 'Aucune cle specifiee.');

            return $this->redirectToRoute('admin_cache_index');
        }

        if (!$this->isCsrfTokenValid('delete_cache_key', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_cache_index');
        }

        $this->logger->info('Cache key deletion requested', ['key' => $key]);

        $deleted = $this->cacheInspector->deleteKey($key);

        if ($deleted) {
            $this->addFlash('success', sprintf('Cle "%s" supprimee.', $key));
        }

        if (!$deleted) {
            $this->addFlash('warning', sprintf('Cle "%s" introuvable.', $key));
        }

        return $this->redirectToRoute('admin_cache_index');
    }

    #[Route('/flush', name: 'flush', methods: ['POST'])]
    public function flush(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('flush_cache', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_cache_index');
        }

        $this->logger->warning('Full cache flush requested');

        $this->cacheInspector->flushAll();

        $this->addFlash('success', 'Cache Redis vide.');

        return $this->redirectToRoute('admin_cache_index');
    }

    private function tryDecodeValue(string $value): ?string
    {
        $unserializedValue = @unserialize($value);

        if ($unserializedValue !== false) {
            $encoded = json_encode($unserializedValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($encoded !== false) {
                return $encoded;
            }
        }

        $jsonDecoded = json_decode($value, true);

        if ($jsonDecoded !== null) {
            $encoded = json_encode($jsonDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($encoded !== false) {
                return $encoded;
            }
        }

        return null;
    }
}
