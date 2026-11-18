<?php

return [
    'timezone' => 'Asia/Jakarta',
    'default_session' => 'regular',
    'shift_start' => '08:00:00',
    'late_grace_minutes' => 0,
    'kiosk' => [
        'session_minutes' => 15,
        'poll_ms' => 250,
        'stable_ms' => 1000,
        'cooldown_ms' => 6500, // At most 9 recognition attempts/minute, below the route limit.
        'absence_ms' => 1200,
    ],
    // One regular schedule, expressed as local business time.
    'schedule' => [
        'previous_day_checkout_until' => '05:00:00', // Exclusive; before this time belongs to yesterday.
        'check_in_from' => '06:00:00',             // Inclusive; the preceding interval is closed.
        'check_out_from' => '17:00:00',            // Inclusive; checkout continues until midnight.
    ],
];
