<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Demo autofill
    |--------------------------------------------------------------------------
    |
    | For live presentations: typing "." into an empty decision reason or
    | next-action field types the prepared text for that batch. Off by
    | default; scripts/demo.sh turns it on for rehearsals.
    |
    */

    'autofill' => (bool) env('SMARTFLOW_DEMO_AUTOFILL', false),

];
