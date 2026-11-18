<?php

it('reports the app is up', function () {
    $this->get('/up')->assertOk();
});

it('serves the api under /api/v1', function () {
    $this->getJson('/api/v1/does-not-exist')->assertNotFound();
});
