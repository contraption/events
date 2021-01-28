<?php

namespace Contraption\Events;

use Closure;
use Contraption\Collections\Collections;
use Contraption\Collections\Contracts\Map;
use Contraption\Events\Attributes\Event;
use Contraption\Events\Attributes\Listener;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use RuntimeException;

class Events
{
    private Map $buses;

    public function __construct()
    {
        $this->buses = Collections::map();
    }

    public function create(string $name, ?Contracts\EventBus $eventBus = null): Contracts\EventBus
    {
        if ($this->buses->has($name)) {
            throw new InvalidArgumentException(sprintf('Event bus %s already exists', $name));
        }

        $eventBus ??= new EventBus;

        $this->buses->put($name, $eventBus);

        return $eventBus;
    }

    public function listen(string $listener): static
    {
        if (! class_exists($listener)) {
            throw new InvalidArgumentException(sprintf('Provided listener %s is not a valid class', $listener));
        }

        try {
            $reflection = new ReflectionClass($listener);
            $attributes = $reflection->getAttributes(Listener::class);

            if (count($attributes) === 0) {
                throw new InvalidArgumentException(
                    sprintf('Provided listener %s does have not have the %s attribute', $listener, Listener::class)
                );
            }

            if (count($attributes) > 1) {
                throw new InvalidArgumentException(
                    sprintf('Listeners can only use the %s attribute once', Listener::class)
                );
            }

            $attribute = $attributes[0]->newInstance();
            $bus       = $this->bus($attribute->getEventBus());

            if ($bus === null) {
                throw new InvalidArgumentException(
                    sprintf('Event bus %s does not exist', $attribute->getEventBus())
                );
            }

            $bus->register($listener);
        } catch (ReflectionException $e) {
            throw new RuntimeException(sprintf('Unable to register listener %s', $listener), previous: $e);
        }

        return $this;
    }

    public function bus(string $name): ?Contracts\EventBus
    {
        return $this->buses->get($name);
    }

    public function fire(object $event, Closure $responseHandler = null): mixed
    {
        $reflection = new ReflectionObject($event);
        $attributes = $reflection->getAttributes(Event::class);

        if (count($attributes) === 0) {
            throw new InvalidArgumentException(
                sprintf('Provided event %s does have not have the %s attribute', $event::class, Event::class)
            );
        }

        if (count($attributes) > 1) {
            throw new InvalidArgumentException(
                sprintf('Events can only use the %s attribute once', Event::class)
            );
        }

        $attribute = $attributes[0]->newInstance();
        $bus       = $this->bus($attribute->getEventBus());

        if ($bus === null) {
            throw new InvalidArgumentException(
                sprintf('Event bus %s does not exist', $attribute->getEventBus())
            );
        }

        return $bus->fire($event, $responseHandler);
    }
}