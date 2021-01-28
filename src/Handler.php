<?php

namespace Contraption\Events;

class Handler implements Contracts\Handler
{
    private string $subject;

    private string $listener;

    private string $method;

    private bool $static;

    public function __construct(string $subject, string $listener, string $method, bool $static)
    {
        $this->subject  = $subject;
        $this->listener = $listener;
        $this->method   = $method;
        $this->static   = $static;
    }

    public function getHandler(): string
    {
        return $this->listener;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function handle(?object $handler, object $subject)
    {
        return call_user_func([$handler ?? $this->getHandler(), $this->method], $subject);
    }

    public function isStatic(): bool
    {
        return $this->static;
    }
}