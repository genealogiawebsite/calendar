<?php

namespace LaravelEnso\Calendar\app\Services\Frequency;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use LaravelEnso\Calendar\app\Enums\Frequencies;
use LaravelEnso\Calendar\app\Enums\UpdateType;
use LaravelEnso\Calendar\app\Models\Event;
use LaravelEnso\Calendar\app\Services\Sequence;

class Update extends Frequency
{
    private static $attributes = ['start_time', 'end_time', 'recurrence_ends_at'];

    private $rootEvent;
    private $changes;
    private $updateType;

    public function handle($changes, $updateType)
    {
        $this->changes = $changes;
        $this->updateType = $updateType;

        $this->init()
            ->break()
            ->update()
            ->insert()
            ->delete();
    }

    private function break()
    {
        switch ($this->updateType) {
            case UpdateType::OnlyThisEvent:
                (new Sequence($this->event))->break($this->event, 1);
                break;
            case UpdateType::ThisAndFutureEvents:
                (new Sequence($this->event))->break($this->event);
        }

        return $this;
    }

    private function update()
    {
        collect($this->changes)->only(static::$attributes)
            ->reject(function ($value, $attribute) {
                return $value === $this->event->{$attribute};
            })
            ->merge($this->changeDates())
            ->whenNotEmpty(function ($attributes) {
                Event::sequence($this->rootEvent->id)
                    ->update($attributes->toArray());
            });

        $this->event->update($this->changes);
        $this->rootEvent->refresh();

        return $this;
    }

    private function insert()
    {
        $eventDates = $this->eventDates();

        $this->interval()
            ->reject(function ($date) use ($eventDates) {
                return $eventDates->contains($date->toDateString());
            })->map(function ($date) {
                return $this->replicate($date)->attributesToArray();
            })->whenNotEmpty(function ($events) {
                Event::insert($events->toArray());
            });

        return $this;
    }

    private function delete()
    {
        $interval = $this->interval()->map->toDateString();

        $this->rootEvent->events
            ->reject(function (Event $event) use ($interval) {
                return $interval->contains($event->start_date->toDateString());
            })->whenNotEmpty(function ($events) {
                Event::whereIn('id', $events->pluck('id'))->delete();
            });
    }

    private function changeDates()
    {
        return collect($this->changes)->only(['start_date', 'end_date'])
            ->map(function ($date, $attribute) {
                return $this->event->{$attribute}->startOfDay()
                    ->diffInDays($this->changes[$attribute], false);
            })->filter()->map(function ($deltaDay, $attribute) {
                return $this->addDays($attribute, $deltaDay);
            });
    }

    private function eventDates()
    {
        return collect([$this->rootEvent])
            ->concat($this->rootEvent->events)
            ->map(function (Event $event) {
                return $event->start_date->toDateString();
            });
    }

    private function interval()
    {
        return $this->dates(
            $this->changes['frequency'] ?? $this->event->frequency,
            $this->rootEvent->start_date,
            $this->rootEvent->recurrence_ends_at ?? $this->rootEvent->start_date
        );
    }

    private function addDays($attribute, $deltaDay)
    {
        return DB::getDriverName() === 'sqlite'
            ? DB::raw("DATE({$attribute}, '{$deltaDay} DAY')")
            : DB::raw("DATE_ADD({$attribute}, INTERVAL {$deltaDay} DAY)");
    }

    private function init()
    {
        $this->rootEvent = $this->updateType === UpdateType::All
            ? $this->parent()
            : $this->event;

        $this->changes = collect($this->changes)
            ->map(function ($value, $attribute) {
                return in_array($attribute, $this->event->getDates())
                    ? Carbon::parse($value)
                    : $value;
            })->toArray();

        if ($this->isExtractFromSequence()) {
            $this->changes['frequency'] = Frequencies::Once;
            $this->changes['recurrence_ends_at'] = null;
        }

        return $this;
    }

    private function isExtractFromSequence()
    {
        return $this->updateType === UpdateType::OnlyThisEvent
            && (int) $this->event->frequency !== Frequencies::Once;
    }
}
