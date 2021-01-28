<?php

namespace Contraption\Events\Concerns;

trait IsCancellable
{
    protected bool $canceled = false;

    protected ?string $cancellationMessage = null;

    public function setCancelled(bool $canceled, ?string $message = null): void
    {
        $this->canceled            = $canceled;
        $this->cancellationMessage = $message;
    }

    public function isCancelled(): bool
    {
        return $this->canceled;
    }

    public function getCancellationMessage(): ?string
    {
        return $this->cancellationMessage;
    }
}