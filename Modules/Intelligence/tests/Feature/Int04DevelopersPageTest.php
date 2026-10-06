<?php

it('serves the public developer documentation without authentication', function (): void {
    $this->get('/developers')
        ->assertOk()
        ->assertSee('Developer documentation')
        ->assertSee('/hardware/scan', false)
        ->assertSee('/openapi.json', false);
});
