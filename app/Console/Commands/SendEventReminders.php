<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Notifications\EventReminder;
use Illuminate\Console\Command;

/**
 * Notifies networking-enabled attendees roughly a day before an event starts.
 *
 * Runs hourly (see routes/console.php) against a moving 23-25h window rather
 * than "starts_at = tomorrow", so a shift in when the command happens to run
 * never causes an event to be skipped entirely. `reminder_sent_at` makes each
 * event eligible for exactly one reminder regardless of how many times the
 * command runs while it sits in that window.
 */
class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Notify networking-enabled attendees of events starting in about a day';

    public function handle(): int
    {
        $events = Event::query()
            ->published()
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now()->addHours(23), now()->addHours(25)])
            ->get();

        foreach ($events as $event) {
            $attendees = $event->eventAttendees()->networking()->with('user')->get()->pluck('user')->filter();

            foreach ($attendees as $attendee) {
                $attendee->notify(new EventReminder($event));
            }

            $event->forceFill(['reminder_sent_at' => now()])->save();

            $this->info("Reminded {$attendees->count()} attendee(s) of \"{$event->title}\".");
        }

        return self::SUCCESS;
    }
}
