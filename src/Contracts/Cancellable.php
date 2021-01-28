<?php

namespace Contraption\Events\Contracts;

interface Cancellable
{
    public function setCancelled(bool $canceled, ?string $message = null);

    public function isCancelled(): bool;

    public function getCancellationMessage(): ?string;
}