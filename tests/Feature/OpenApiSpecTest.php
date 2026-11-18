<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

/**
 * docs/api/openapi.json is the contract the frontend team builds against.
 * It is generated from the code, so it must be regenerated whenever an
 * endpoint, request or response changes:
 *
 *     composer docs
 *
 * This test fails when the committed file is out of date, so a change to the
 * API can never be merged without the contract (and its diff) being reviewed.
 */
function generatedOpenApi(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lems-openapi-'.uniqid().'.json';

    Artisan::call('scramble:export', ['--path' => $path]);

    $json = (string) file_get_contents($path);
    @unlink($path);

    return str_replace("\r\n", "\n", $json);
}

it('matches the committed docs/api/openapi.json', function () {
    $file = base_path('docs/api/openapi.json');
    $generated = generatedOpenApi();

    // `composer docs` passes --update-snapshots to rewrite the file.
    if (in_array('--update-snapshots', $_SERVER['argv'] ?? [], true)) {
        file_put_contents($file, $generated);
    }

    $committed = str_replace("\r\n", "\n", (string) file_get_contents($file));

    expect($generated === $committed)->toBeTrue('docs/api/openapi.json is out of date. Run: composer docs');
});

it('documents every API route, with bearer auth on protected ones only', function () {
    $spec = json_decode(generatedOpenApi(), true);

    $documented = collect($spec['paths'])->flatMap(fn ($ops, $path) => collect($ops)->map(fn ($op, $method) => strtoupper($method).' '.$path)->values());
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/'))
        ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS']))
            ->map(fn ($m) => $m.' /'.substr($r->uri(), strlen('api/v1/'))));

    expect($documented->sort()->values()->all())->toBe($routes->sort()->values()->all());

    expect($spec['components']['securitySchemes'])->toHaveKey('http');
    expect($spec['paths']['/auth/login']['post']['security'])->toBe([]) // public
        ->and($spec['paths']['/me']['get'])->not->toHaveKey('security'); // inherits bearer
});

it('describes every error with the {message, errors, code} envelope', function () {
    $spec = json_decode(generatedOpenApi(), true);
    $checked = 0;

    foreach ($spec['paths'] as $operations) {
        foreach ($operations as $operation) {
            foreach ($operation['responses'] as $status => $response) {
                if ((int) $status >= 400) {
                    $checked++;
                    expect($response['content']['application/json']['schema']['$ref'])->toBe('#/components/schemas/ErrorResponse');
                }
            }
        }
    }

    expect($checked)->toBeGreaterThan(20)
        ->and($spec['components']['schemas']['ErrorResponse']['required'])->toBe(['message', 'errors', 'code']);
});

it('serves the interactive docs only on local and staging', function () {
    foreach (['local' => true, 'staging' => true, 'production' => false, 'testing' => false] as $environment => $allowed) {
        app()['env'] = $environment;

        expect(Gate::forUser(null)->allows('viewApiDocs'))->toBe($allowed, "viewApiDocs in {$environment}");
    }
});
