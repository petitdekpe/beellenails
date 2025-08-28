<?php

namespace App\Schedule;

use App\Message\SendReminderEmailsMessage;
use App\Message\SendTomorrowReminderEmailsMessage;
use App\Message\SendDailyAppointmentsEmailMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('reminder_emails')]
class ReminderEmailSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Rappel 3 jours avant - tous les jours à 18h00 (6 PM)
                RecurringMessage::cron('0 18 * * *', new SendReminderEmailsMessage())
            )
            ->add(
                // Rappel la veille - tous les jours à 6h30 (6:30 AM)
                RecurringMessage::cron('30 6 * * *', new SendTomorrowReminderEmailsMessage())
            )
            ->add(
                // Envoi liste des rendez-vous du lendemain à l'admin - tous les jours à 20h30 (8:30 PM)
                RecurringMessage::cron('30 20 * * *', new SendDailyAppointmentsEmailMessage())
            );
    }
}