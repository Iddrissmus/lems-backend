<?php

/*
|--------------------------------------------------------------------------
| Multi-tenancy (BE-001): built in, switched off
|--------------------------------------------------------------------------
|
| Every domain table carries a tenant_id and every query is scoped to the
| current tenant. With TENANCY_ENABLED=false (the default) there is exactly
| one tenant, the default one below, and nothing changes for users. The plan
| is to resell the platform to other colleges later; having the column and
| the scope from day one avoids touching every query then.
|
| Do NOT switch this on yet: a second college also needs a way to find the
| tenant for requests that have no signed-in user (sign-in, password reset,
| invitation links), for example by host name. See docs/decisions/0010.
|
*/

return [

    'enabled' => (bool) env('TENANCY_ENABLED', false),

    // The tenant that owns everything while tenancy is off (created by a migration).
    'default_slug' => 'scnmtc',

];
