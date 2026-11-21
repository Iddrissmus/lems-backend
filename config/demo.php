<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo account password
    |--------------------------------------------------------------------------
    |
    | The password given to every synthetic demo account (<role>@demo.test and the
    | seeded students and lecturers) when the demo seeders run. It is used ONLY by
    | those seeders, which refuse to run outside the local environment. Set it in
    | .env before the API is reachable from outside (for example through a tunnel),
    | so a stranger who finds the address cannot sign in with the well known
    | default. Never put a real person's password here. Running the seeders again
    | applies a new value to the existing demo accounts.
    |
    */

    'password' => env('DEMO_PASSWORD') ?: 'password',

];
