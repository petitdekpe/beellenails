<?php

namespace App\Scheduler;

use App\Message\Foo;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule(name: 'default')]
class Scheduler implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every('30 seconds', new Foo())
        );
    }
}
