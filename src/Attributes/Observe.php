<?php

namespace Contraption\Events\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Observe
{
    private string $action;

    public function __construct(string $action)
    {
        $this->action = $action;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }
}