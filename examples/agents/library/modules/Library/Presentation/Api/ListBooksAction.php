<?php

declare(strict_types=1);

namespace Modules\Library\Presentation\Api;

use Modules\Library\Application\Features\ListBooks\ListBooksQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zana\Http\ResponseFactory;
use Zana\Mediator\MediatorInterface;
use Zana\Routing\Attribute\Route;

/**
 * GET /api/library/list-books: sends ListBooksQuery with the values of the query string and
 * answers {"data": …}, or 422 with the field errors when they are invalid.
 */
final readonly class ListBooksAction
{
    public function __construct(
        private ApiInput $input,
        private MediatorInterface $mediator,
        private ResponseFactory $responses,
    ) {
    }

    #[Route('/api/library/list-books', methods: ['GET'], name: 'library.list-books')]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responses->json(['data' => $this->mediator->send($this->input->read($request, ListBooksQuery::class))]);
    }
}
