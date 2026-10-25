<?php

declare(strict_types=1);

namespace Modules\Appointments\Presentation\Api;

use Clinic\Http\JsonInput;
use Modules\Appointments\Application\AppointmentRejected;
use Modules\Appointments\Application\BookAppointment;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zana\Http\Exception\UnprocessableEntityHttpException;
use Zana\Http\ResponseFactory;
use Zana\Mediator\MediatorInterface;
use Zana\Validation\ValidationException;

// region: action
/**
 * POST /api/appointments {"patient_id": "…", "starts_at": "2030-01-15T09:30:00+00:00", "reason": "Check-up"}
 */
final readonly class BookAppointmentAction
{
    public function __construct(private MediatorInterface $mediator, private JsonInput $input, private ResponseFactory $responses)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $appointment = $this->mediator->send($this->input->read($request, BookAppointment::class));
        } catch (ValidationException $e) {
            throw JsonInput::invalid($e, BookAppointment::class);
        } catch (AppointmentRejected $e) {
            throw new UnprocessableEntityHttpException($e->errors, previous: $e);
        }

        return $this->responses->json($appointment, 201);
    }
}
// endregion: action
