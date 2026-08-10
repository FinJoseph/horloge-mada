<?php

return [
    'timezone' => env('SHIFT_TIMEZONE', 'Indian/Antananarivo'),

    'start' => env('SHIFT_START', '07:00'),

    'lunch' => env('SHIFT_LUNCH', '12:00'),

    'lunch_duration' => env('SHIFT_LUNCH_DURATION', 60),

    'end' => env('SHIFT_END', '19:00'),
];
