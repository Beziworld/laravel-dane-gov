<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Exceptions;

/**
 * Raised when the API answers HTTP 404 (unknown dataset, resource, institution, showcase or row).
 */
final class NotFound extends ApiException {}
