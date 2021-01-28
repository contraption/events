<?php

namespace Contraption\Events;

use Closure;
use Contraption\Collections\Collections;
use Contraption\Collections\Contracts\Map;
use Contraption\Collections\Contracts\Sequence;
use Contraption\Collections\MultiMap;
use Contraption\Events\Attributes\Subscribe;
use Contraption\Events\Contracts\Cancellable;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

class EventBus implements Contracts\EventBus
{
    private Map $listenerInstances;

    private Sequence $listeners;

    private MultiMap $eventHandlers;

    private ?Closure $instanceCreator = null;

    public function __construct()
    {
        $this->listenerInstances = Collections::map();
        $this->listeners         = Collections::sequence();
        $this->eventHandlers     = Collections::multiMap();
    }

    public function fire(object $event, Closure $responseHandler = null): object
    {
        $eventClass    = $event::class;
        $eventHandlers = $this->getHandlersForEvent($eventClass);
        $cancellable   = $this->isEventCancellable($event);

        foreach ($eventHandlers as $eventHandler) {
            if ($eventHandler->isStatic()) {
                $response = $eventHandler->handle(subject: $event);
            } else {
                $response = $eventHandler->handle(
                    $this->getListenerInstance($eventHandler->getHandler()),
                    $event
                );
            }

            if ($responseHandler !== null && ! $responseHandler($response)) {
                break;
            }

            if ($cancellable && $event->isCancelled()) {
                break;
            }
        }

        return $event;
    }

    public function register(string|object $listener): static
    {
        if (is_object($listener)) {
            if ($listener instanceof Closure) {
                throw new InvalidArgumentException('Event listeners cannot be closures');
            }

            $listenerClass = $listener::class;
            $this->listenerInstances->put($listenerClass, $listener);
            $listener = $listenerClass;
        }

        $this->registerHandlersForListener($listener);
        $this->listeners->add($listener);

        return $this;
    }

    public function unregister(string $listener): static
    {
        if ($this->listeners->contains($listener)) {
            $this->listeners->remove($this->listeners->find($listener));
            $this->listenerInstances->remove($listener);
            $this->eventHandlers->map(function (Sequence $handlers, string $event) use ($listener) {
                $handlers->filter(function (Handler $handler) use ($listener) {
                    return $handler->getHandler() !== $listener;
                });
            });
        }

        return $this;
    }

    private function getHandlersForEvent(string $eventClass): Sequence
    {
        return $this->eventHandlers->copy()->filter(function (Handler $handler) use ($eventClass) {
            return $handler->getSubject() === $eventClass || is_subclass_of($eventClass, $handler->getSubject());
        })->flatten();
    }

    private function isEventCancellable(object $event): bool
    {
        return $event instanceof Cancellable;
    }

    private function getListenerInstance(string $listener): object
    {
        if (! $this->listenerInstances->has($listener)) {
            $creator          = $this->getInstanceCreator();
            $listenerInstance = $creator($listener);
            $this->listenerInstances->put($listener, $listenerInstance);
        }

        return $this->listenerInstances->get($listener);
    }

    private function getInstanceCreator(): Closure
    {
        return $this->instanceCreator ?? static fn($class) => new $class;
    }

    private function registerHandlersForListener(string $listener): void
    {
        try {
            $reflection = new ReflectionClass($listener);
            $handlers   = Collections::sequence(
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC)
            )->filter(function (ReflectionMethod $method) {
                return count($method->getAttributes(Subscribe::class)) === 1;
            })->map(function (ReflectionMethod $method) use ($listener) {
                return $this->createHandler($listener, $method);
            })->each(function (Handler $handler) {
                $this->eventHandlers->put($handler->getSubject(), $handler);
            })->count();

            if ($handlers === 0) {
                throw new InvalidArgumentException(sprintf('Listener %s has 0 event handler methods', $listener));
            }
        } catch (ReflectionException $e) {
            throw new RuntimeException('Unable to register listener', previous: $e);
        }
    }

    private function createHandler(string $listener, ReflectionMethod $method): Contracts\Handler
    {
        if ($method->getNumberOfParameters() !== 1) {
            throw new InvalidArgumentException('Event handlers must have exactly 1 parameter');
        }

        $parameter = $method->getParameters()[0];

        if (! $parameter->hasType()) {
            throw new InvalidArgumentException('Listeners must use the event as a parameter type');
        }

        $eventType = $parameter->getType();

        if (! ($eventType instanceof ReflectionNamedType)) {
            throw new InvalidArgumentException('Listeners must use the event as a named parameter type');
        }

        if ($eventType->isBuiltin()) {
            throw new InvalidArgumentException('Events cannot use built in types');
        }

        $eventName = $eventType->getName();

        return new Handler($eventName, $listener, $method->getName(), $method->isStatic());
    }

    public function setInstanceCreator(Closure $instanceCreator): static
    {
        $this->instanceCreator = $instanceCreator;

        return $this;
    }
}