<?php

namespace LaravelEnso\Calendar\app\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use LaravelEnso\TrackWho\app\Traits\CreatedBy;

class Reminder extends Model
{
    use CreatedBy;

    protected $fillable = ['event_id', 'remind_at', 'reminded_at'];

    protected $dates = ['remind_at'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeReadyForNotify($query)
    {
        return $query->whereNull('reminded_at')
            ->where('remind_at', '<=', Carbon::now());

    }

    public function setRemindAtAttribute($value)
    {
        $this->attributes['remind_at'] =
            Carbon::parse($value)->format('Y-m-d H:i:s');
    }
}
