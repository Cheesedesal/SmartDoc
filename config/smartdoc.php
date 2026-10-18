<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request automation ("SmartDoc AI")
    |--------------------------------------------------------------------------
    |
    | tick_seconds       How often (at most) a normal page load may trigger an automation
    |                    pass. 0 disables the page-load trigger (the scheduler and the
    |                    smartdoc:automate-requests command still work).
    | min_stage_seconds  Lower bound for one processing stage. A document's
    |                    processing_days are split evenly over its three working stages.
    |
    */

    'automation' => [
        'tick_seconds' => (int) env('SMARTDOC_AUTOMATION_TICK_SECONDS', 60),
        'min_stage_seconds' => (int) env('SMARTDOC_MIN_STAGE_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads and payment proof
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'disk' => 'local',            // private disk (storage/app); never the public disk
        'max_kb' => 5120,
        'min_image_side' => 100,      // px; smaller images are treated as unreadable
    ],

    'payment' => [
        'max_age_days' => 30,         // a payment older than this is flagged
    ],

];
