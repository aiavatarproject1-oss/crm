<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pipeline events (inbound → vision → chat trace)
    |--------------------------------------------------------------------------
    |
    | Every PipelineMonitor event is written to storage/logs/pipeline.log AND
    | (when `store` is true) to the `pipeline_events` collection so the admin
    | panel can search it. Documents expire automatically after `ttl_days`.
    | When `broadcast` is true each event is also pushed over Reverb to the
    | private `admin.pipeline` channel for the live log view.
    |
    */

    'pipeline_events' => [
        'store' => (bool) env('PIPELINE_EVENTS_STORE', true),
        'broadcast' => (bool) env('PIPELINE_EVENTS_BROADCAST', true),
    ],

    'pipeline_events_ttl_days' => (int) env('PIPELINE_EVENTS_TTL_DAYS', 30),

];
