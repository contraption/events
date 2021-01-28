<?php

namespace Contraption\Events\Contracts;

interface Observable
{
    public static function registerObserver(string|object $observer): void;

    public static function unregisterObserver(string $observer): void;

    public function notifyObservers(string $action): bool;
}