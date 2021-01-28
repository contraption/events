<?php

namespace Contraption\Events\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Event
{
    private string $eventBus;

    public function __construct(string $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    /**
     * @return string
     */
    public function getEventBus(): string
    {
        return $this->eventBus;
    }
}