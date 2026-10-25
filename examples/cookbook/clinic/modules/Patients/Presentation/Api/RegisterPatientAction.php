<?php

declare(strict_types=1);

namespace Modules\Patients\Presentation\Api;

use Clinic\Http\JsonInput;
use Modules\Patients\Application\RegisterPatient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zana\Http\ResponseFactory;
use Zana\Mediator\MediatorInterface;
use Zana\Validation\ValidationException;

/**
 * POST /api/patients {"name": "Ada Lovelace"}
 */
final readonly class RegisterPatientAction
{
    public function __construct(private MediatorInterface $mediator, private JsonInput $input, private ResponseFactory $responses)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $patient = $this->mediator->send($this->input->read($request, RegisterPatient::class));
        } catch (ValidationException $e) {
            throw JsonInput::invalid($e, RegisterPatient::class);
        }

        return $this->responses->json($patient, 201);
    }
}
