<?php

namespace Wexample\SymfonyActivity\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PageController
{
    #[Route(path: '/page', name: 'page')]
    public function page(): Response
    {
        return new Response('<html><body>page</body></html>');
    }

    #[Route(path: '/data', name: 'data')]
    public function data(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route(path: '/_program', name: 'program')]
    public function program(): Response
    {
        return new Response('<html><body>program</body></html>');
    }
}
