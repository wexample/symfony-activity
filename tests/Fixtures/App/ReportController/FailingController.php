<?php

namespace Wexample\SymfonyActivity\Tests\Fixtures\App\ReportController;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class FailingController
{
    #[Route(path: '/boom', name: 'boom')]
    public function boom(): Response
    {
        throw new RuntimeException('The fixture fails.');
    }

    #[Route(path: '/gone', name: 'gone')]
    public function gone(): Response
    {
        throw new NotFoundHttpException();
    }
}
