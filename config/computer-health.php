<?php

return [
    'replica' => (bool) env('COMPUTER_HEALTH_REPLICA', false),
    'replica_captured_at' => env('COMPUTER_HEALTH_SNAPSHOT_AT'),
];
