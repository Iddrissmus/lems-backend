# API contract

The API is described by an OpenAPI document that is **generated from the code**, so it cannot drift from what the server really does.

| What | Where |
|---|---|
| The contract (commit this, share this) | `docs/api/openapi.json` |
| Interactive docs with a "Try it" button | `http://localhost:8000/docs/api` (local and staging only) |
| Same spec, live | `http://localhost:8000/docs/api.json` |

## Viewing it

1. Start the app: `php artisan serve`
2. Open <http://localhost:8000/docs/api>
3. Pick an endpoint on the left. Use **Try it** to send a real request. Paste your token into the authorization box after signing in (see `docs/manual-testing.md`).

The page is switched off in production.

You can also import `docs/api/openapi.json` into Postman, Insomnia or Bruno to get every endpoint as a ready-made request.

## Changing the API

1. Change the code (controller, request, resource).
2. Run `composer docs`. It rewrites `docs/api/openapi.json`.
3. Look at the diff of `openapi.json`. That diff is exactly what the frontend team will see change.
4. Commit the code and the regenerated file together.

If you forget step 2, `composer ci` fails with "docs/api/openapi.json is out of date".

## Rules with the frontend team

- Agree the contract before building each module. They should see the `openapi.json` diff before a change is merged.
- Inside `/api/v1`, a **breaking change** is never allowed once the frontend depends on it. Breaking means: removing or renaming a field or endpoint, making an optional input required, changing a type, or changing a `code`. Adding an optional field or a new endpoint is safe.
- A breaking change needs a new version (`/api/v2`) and a migration plan.

## Writing good docs from the code

- Give each controller a `#[Group('...')]` and each method an `#[Endpoint(title: '...', description: '...')]`. The description says which permission is needed.
- Validation rules in the Form Request become the documented request body.
- Return a Resource (or a clearly shaped array) so the response is documented.
- Error responses are rewritten automatically to the shared `ErrorResponse` schema (`app/Support/Api/ErrorEnvelopeDocumentation.php`).
