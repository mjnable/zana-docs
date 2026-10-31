<?php

declare(strict_types=1);

namespace Modules\Library\Presentation\Api;

use Modules\Library\Application\Features\AddBook\AddBookCommand;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zana\Http\ResponseFactory;
use Zana\Mediator\MediatorInterface;
use Zana\Routing\Attribute\Route;

/**
 * POST /api/library/add-book with a JSON object: sends AddBookCommand. Answers 204 when it
 * is handled, 422 with the field errors when the input is invalid.
 */
final readonly class AddBookAction
{
    public function __construct(
        private ApiInput $input,
        private MediatorInterface $mediator,
        private ResponseFactory $responses,
    ) {
    }

    #[Route('/api/library/add-book', methods: ['POST'], name: 'library.add-book')]
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->mediator->send($this->input->read($request, AddBookCommand::class));

        return $this->responses->noContent();
    }
}
