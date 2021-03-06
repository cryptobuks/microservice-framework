<?php

namespace CrCms\Microservice\Server\Exceptions;

use Throwable;

/**
 * Class ServiceUnavailableException.
 */
class ServiceUnavailableException extends ServiceException
{
    /**
     * ServiceUnavailableException constructor.
     *
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, 503, $previous);
    }
}
