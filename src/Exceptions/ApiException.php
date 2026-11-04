<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Exceptions;

use Throwable;

/**
 * Raised when the dane.gov.pl API rejects a request, answers with an unexpected body or cannot be reached.
 *
 * Error answers follow JSON:API: a list of objects with "status", "code", "title",
 * "detail" and an optional "source.pointer" naming the offending parameter.
 */
class ApiException extends DaneGovException
{
    /**
     * @param list<array<string, mixed>> $errors
     */
    final public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly ?string $path = null,
        public readonly ?string $errorCode = null,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    /**
     * @param array<array-key, mixed>|null $body
     */
    public static function fromResponse(int $status, string $path, ?array $body, string $rawBody = ''): static
    {
        $errors = [];

        foreach (is_array($body['errors'] ?? null) ? $body['errors'] : [] as $error) {
            if (is_array($error)) {
                $item = [];

                foreach ($error as $key => $value) {
                    $item[(string) $key] = $value;
                }

                $errors[] = $item;
            }
        }

        $first = $errors[0] ?? [];
        $parts = [];

        foreach (['title', 'detail'] as $key) {
            if (is_string($first[$key] ?? null) && trim($first[$key]) !== '' && ! in_array(trim($first[$key]), $parts, true)) {
                $parts[] = trim($first[$key]);
            }
        }

        $pointer = is_array($first['source'] ?? null) && is_string($first['source']['pointer'] ?? null) ? $first['source']['pointer'] : null;
        $message = implode(': ', $parts);

        if ($message === '') {
            $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($rawBody)));
            $message = mb_substr($text, 0, 200);
        }

        return new static(
            sprintf('dane.gov.pl API error (HTTP %d) for "%s"%s%s', $status, $path, $message !== '' ? ': ' . $message : '.', $pointer !== null ? sprintf(' (%s)', $pointer) : ''),
            $status,
            $path,
            is_string($first['code'] ?? null) ? $first['code'] : null,
            $errors,
        );
    }

    public static function transport(string $path, Throwable $previous): static
    {
        return new static(sprintf('Could not connect to the dane.gov.pl API (%s): %s', $path, $previous->getMessage()), 0, $path, null, [], $previous);
    }

    public static function unexpectedResponse(string $path, string $reason = 'the body is not a JSON:API document'): static
    {
        return new static(sprintf('The dane.gov.pl API returned an unexpected response for "%s": %s.', $path, $reason), 0, $path);
    }

    public function isServerError(): bool
    {
        return $this->httpStatus >= 500;
    }

    public function isValidationError(): bool
    {
        return $this->httpStatus === 422;
    }

    public function isRateLimited(): bool
    {
        return $this->httpStatus === 429;
    }
}
