<?php

namespace App\MessageHandler;

use App\Message\Foo;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

// src/Handler/FooHandler.php
#[AsMessageHandler]
readonly final class FooHandler
{
    public function __invoke(Foo $foo): void
    {
        sleep(5);
    }
}
