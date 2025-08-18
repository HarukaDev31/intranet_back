<?php

return [
    /*
     * The interval in seconds at which statistics should be logged.
     */
    'interval_in_seconds' => 60,

    /*
     * The interval in seconds at which the cleanup of old statistics should occur.
     */
    'delete_statistics_older_than_days' => 30,

    /*
     * The channel name that should be used to log statistics to.
     */
    'channel_name' => env('APP_NAME', 'laravel') . '_statistics',
];
