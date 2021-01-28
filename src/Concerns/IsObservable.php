<?php

namespace Contraption\Events\Concerns;

use Closure;
use Contraption\Collections\Collections;
use Contraption\Collections\Contracts\Map;
use Contraption\Collections\Contracts\Sequence;
use Contraption\Collections\MultiMap;
use Contraption\Events\Attributes\Observe;
use Contraption\Events\Contracts\Handler as HandlerContract;
use Contraption\Events\Handler;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;

trait IsObservable
{
    private static Map $observerInstances;

    private static Sequence $observers;

    private static MultiMap $actionHandlers;

    private static ?Closure $instanceCreator = null;

    public static function registerObserver(string|object $observer): void
    {
        if (is_object($observer)) {
            if ($observer instanceof Closure) {
                throw new InvalidArgumentException('Observers cannot be closures');
            }

            $observerClass = $observer::class;
            self::getObserverInstances()->put($observerClass, $observer);
            $observer = $observerClass;
        }

        self::registerHandlersForObserver($observer);
        self::getObservers()->add($observer);
    }

    private static function getObserverInstances(): Map
    {
        if (! isset(self::$observerInstances)) {
            self::$observerInstances = Collections::map();
        }

        return self::$observerInstances;
    }

    private static function registerHandlersForObserver(string $observer): void
    {
        try {
            $reflection   = new ReflectionClass($observer);
            $methods      = Collections::sequence(
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC)
            )->filter(function (ReflectionMethod $method) {
                return count($method->getAttributes(Observe::class)) === 1;
            });
            $handlerCount = 0;
            $methods->each(function (ReflectionMethod $method) use (&$handlerCount, $observer) {
                $attributes = $method->getAttributes(Observe::class);

                foreach ($attributes as $attribute) {
                    /** @noinspection PhpParamsInspection */
                    $handler = self::createHandler($observer, $method, $attribute->newInstance());
                    $handlerCount++;
                    self::getActionHandlers()->put($handler->getSubject(), $handler);
                }
            });

            if ($handlerCount === 0) {
                throw new InvalidArgumentException(sprintf('Observer %s has 0 action handler methods', $observer));
            }
        } catch (ReflectionException $e) {
            throw new RuntimeException('Unable to register observer', previous: $e);
        }
    }

    private static function createHandler(string $observer, ReflectionMethod $method, Observe $attribute): HandlerContract
    {
        if ($method->getNumberOfParameters() !== 1) {
            throw new InvalidArgumentException('Observer methods must have exactly 1 parameter');
        }

        return new Handler($attribute->getAction(), $observer, $method->getName(), $method->isStatic());
    }

    private static function getActionHandlers(): MultiMap
    {
        if (! isset(self::$actionHandlers)) {
            self::$actionHandlers = Collections::multiMap();
        }

        return self::$actionHandlers;
    }

    private static function getObservers(): Sequence
    {
        if (! isset(self::$observers)) {
            self::$observers = Collections::sequence();
        }

        return self::$observers;
    }

    public static function unregisterObserver(string $observer): void
    {
        if (self::getObservers()->contains($observer)) {
            self::getObservers()->remove(self::getObservers()->find($observer));
            self::getObserverInstances()->remove($observer);
            self::getActionHandlers()->map(function (Sequence $handlers, string $action) use ($observer) {
                $handlers->filter(function (Handler $handler) use ($observer) {
                    return $handler->getHandler() !== $observer;
                });
            });
        }
    }

    public static function setInstanceCreator(Closure $instanceCreator): void
    {
        self::$instanceCreator = $instanceCreator;
    }

    public function notifyObservers(string $action): void
    {
        $handlers = self::getActionHandlers()->get($action);

        if ($handlers->count() > 0) {
            foreach ($handlers as $handler) {
                if ($handler->isStatic()) {
                    $handler->handle(subject: $this);
                } else {
                    $handler->handle(
                        self::getListenerInstance($handler->getHandler()),
                        $this
                    );
                }
            }
        }
    }

    private static function getListenerInstance(string $listener): object
    {
        if (! self::getObserverInstances()->has($listener)) {
            $creator          = self::getInstanceCreator();
            $listenerInstance = $creator($listener);
            self::getObserverInstances()->put($listener, $listenerInstance);
        }

        return self::getObserverInstances()->get($listener);
    }

    private static function getInstanceCreator(): Closure
    {
        return self::$instanceCreator ?? static fn($class) => new $class;
    }
}