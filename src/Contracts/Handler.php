<?php

namespace Contraption\Events\Contracts;

interface Handler
{
    public function getSubject(): string;

    public function getHandler(): string;

    public function isStatic(): bool;

    public function handle(?object $handler, object $subject);
}