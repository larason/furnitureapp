<?php

namespace App\Exceptions;

use App\Logging\SanitizeApiExceptionLogger;
use Illuminate\Foundation\Exceptions\Handler as LaravelHandler;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

final class Handler extends LaravelHandler
{
    protected function newLogger(): LoggerInterface
    {
        $request = $this->container->bound('request') ? $this->container->make('request') : null;

        return new SanitizeApiExceptionLogger(
            parent::newLogger(),
            $request instanceof Request ? $request : null,
        );
    }
}
