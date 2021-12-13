<?php

declare(strict_types=1);


namespace Scpzc\HyperfDb\Event;
use Throwable;

class FailToExecute
{

    /**
     * @var Throwable
     */
    public $throwable;

    public function __construct(Throwable $throwable)
    {
        $this->throwable = $throwable;
    }
}
