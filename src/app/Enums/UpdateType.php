<?php

namespace LaravelEnso\Calendar\app\Enums;

use LaravelEnso\Enums\app\Services\Enum;

class UpdateType extends Enum
{
    const Single = 'single';
    const Futures = 'futures';
    const All = 'all';
}
