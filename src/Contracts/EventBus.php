<?php

namespace Contraption\Events\Contracts;

use Closure;

interface EventBus
{
    /**
     * @param string|class-string|object $listener
     *
     * @return static
     */
    public function register(string|object $listener): static;

    /**
     * @param string|class-string $listener
     *
     * @return static
     */
    public function unregister(string $listener): static;

    public function fire(object $event, Closure $responseHandler = null): object;
}