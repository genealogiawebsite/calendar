<?php

namespace LaravelEnso\Calendar\app\Services\Frequencies;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LaravelEnso\Calendar\app\Models\Event;

abstract class Frequency
{
    protected $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    abstract protected function dates(): Collection;

    public function update()
    {
        $this->insert()->updateTimes()->deleteTimes();
    }

    public function delete()
    {
        Event::whereParentId($this->parent()->id)
            ->orWhere('id', $this->parent()->id)
            ->delete();
    }

    protected function interval()
    {
        $start = $this->parent()->start();
        $end = $this->event->recurrenceEnds();

        return collect($start->daysUntil($end)->toArray());
    }

    public function insert()
    {
        $events = $this->dates()->filter(function ($date) {
                return $this->events()->first(function (Event $event) use ($date) {
                    echo $date->toDateString().' === '. $event->start()->toDateString().PHP_EOL;
                    return $date->toDateString() === $event->start()->toDateString();
                }) === null;
            })->map(function ($date) {
                return $this->event->replicate(['id'])->fill([
                    'parent_id' => $this->event->id,
                    'starts_at' => $this->event->start()->setDateFrom($date),
                    'ends_at' => $this->event->end()->setDateFrom($date),
                ]);
            });

        Event::insert($events->map->attributesToArray()->toArray());

        return $this;
    }

    protected function updateTimes()
    {
        $updatable = collect();

        if ($this->event->wasChanged('starts_at')) {
            $updatable->put('starts_at',
                DB::raw("CONCAT(DATE(starts_at), ' {$this->event->start()->toTimeString()}')"));
        }

        if ($this->event->wasChanged('ends_at')) {
            $updatable->put('ends_at',
                DB::raw("CONCAT(DATE(ends_at), ' {$this->event->end()->toTimeString()}')"));
        }

        if ($this->event->wasChanged('recurrence_ends_at')) {
            $updatable->put('recurrence_ends_at', $this->event->recurrenceEnds());
        }

        if ($updatable->isNotEmpty()) {
            Event::whereId($this->parent()->id)
                ->orWhere('parent_id', $this->parent()->id)
                ->update($updatable->toArray());
        }

        return $this;
    }

    protected function deleteTimes()
    {
        Event::whereParentId($this->parent()->id)
            ->where(function ($query) {
                $query->where('starts_at', '<', $this->parent()->start())
                    ->orWhere('ends_at', '>', $this->event->recurrenceEnds());
            })->delete();

        return $this;
    }

    protected function start()
    {
        return $this->event->start();
    }

    protected function parent()
    {
        return $this->isParent()
            ? $this->event->parent
            : $this->event;
    }

    protected function isParent()
    {
        return $this->event->parent_id;
    }

    private function events()
    {
        return collect([$this->parent()])
            ->concat($this->parent()->events);
    }
}
