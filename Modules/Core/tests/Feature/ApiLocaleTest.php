<?php

use Modules\Core\Http\Middleware\SetApiLocale;

it('negotiates Accept-Language to English, chiShona or isiNdebele by quality', function (string $header, string $expected): void {
    expect(SetApiLocale::negotiate($header))->toBe($expected);
})->with([
    'empty falls back' => ['', 'en'],
    'shona' => ['sn', 'sn'],
    'region tag' => ['nd-ZW', 'nd'],
    'quality wins' => ['en;q=0.4, sn;q=0.9', 'sn'],
    'unsupported then supported' => ['fr-FR, nd;q=0.5', 'nd'],
    'all unsupported' => ['de, fr', 'en'],
    'zero quality refused' => ['sn;q=0', 'en'],
]);

it('echoes the negotiated language on API responses', function (): void {
    $this->getJson('/api/v1/me', ['Accept-Language' => 'sn-ZW,en;q=0.5'])->assertStatus(401)->assertHeader('Content-Language', 'sn');
    $this->getJson('/api/v1/me')->assertHeader('Content-Language', 'en');
});
