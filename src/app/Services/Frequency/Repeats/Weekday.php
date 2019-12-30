<?php

namespace LaravelEnso\Calendar\App\Services\Frequency\Repeats;

use Illuminate\Support\Collection;

class Weekday extends Repeat
{
    public function dates(): Collection
    {
        return $this->interval()->filter->isWeekDay();
    }
}
