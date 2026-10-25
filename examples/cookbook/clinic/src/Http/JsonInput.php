<?php

declare(strict_types=1);

namespace Clinic\Http;

use Psr\Http\Message\ServerRequestInterface;
use Zana\Http\Exception\BadRequestHttpException;
use Zana\Http\Exception\UnprocessableEntityHttpException;
use Zana\Serialization\Attribute\SerializedName;
use Zana\Serialization\DenormalizationException;
use Zana\Serialization\Serializer;
use Zana\Validation\ValidationException;

/**
 * Turns a JSON body into a typed message, and input failures into a 422
 * with field errors under the public (JSON) names.
 */
final readonly class JsonInput
{
    public function __construct(private Serializer $serializer)
    {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function read(ServerRequestInterface $request, string $class): object
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            throw new BadRequestHttpException('The request body must be a JSON object.');
        }

        try {
            return $this->serializer->denormalize($data, $class);
        } catch (DenormalizationException $e) {
            throw new UnprocessableEntityHttpException($e->errors(), previous: $e);
        }
    }

    /**
     * A 422 for a failed validation, with each property renamed to its JSON name.
     *
     * @param class-string $class
     */
    public static function invalid(ValidationException $e, string $class): UnprocessableEntityHttpException
    {
        $errors = [];
        foreach ($e->violations->toArray() as $path => $messages) {
            $attribute = property_exists($class, $path) ? (new \ReflectionProperty($class, $path))->getAttributes(SerializedName::class)[0] ?? null : null;
            $errors[$attribute?->newInstance()->name ?? $path] = $messages;
        }

        return new UnprocessableEntityHttpException($errors, previous: $e);
    }
}
