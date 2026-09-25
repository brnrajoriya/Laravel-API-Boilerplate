# Laravel API Boilerplate

[![CI](https://github.com/brnrajoriya/Laravel-API-Boilerplate/actions/workflows/ci.yml/badge.svg)](https://github.com/brnrajoriya/Laravel-API-Boilerplate/actions/workflows/ci.yml)

A production-ready **Laravel 13** REST API starter with the configuration every project needs already
done: token authentication, file uploads, a full CRUD generator, one JSON response format, policies,
API resources, rate limiting, API docs, tests and CI.

List endpoints are powered by **[QueryFlow](https://github.com/brnrajoriya/laravel-queryflow)**: declare
a whitelist on the model and every list endpoint gets pagination, sorting, search, filters, relations,
counts and aggregates - safely.

It pairs with the [Angular Boilerplate](https://github.com/brnrajoriya/Angular-Boilerplate) frontend
(same demo account, same resource examples), but it is a complete project on its own.

Created by [Bhaskar Rajoriya](https://www.linkedin.com/in/brnrajoriya/).

---

## Features

- **Auth with Laravel Sanctum** - register, login, logout, me, update profile, change password, forgot / reset password (link to your frontend); expiring tokens, one per device
- **One response envelope** for every success and error: `{ status, data, errors, hasError, message }`
- **Global error handling** - 422, 401, 403, 404, 405, 429 and 500 all use the envelope; no `try/catch` in controllers; internal details hidden in production
- **One-command resources** - `php artisan make:model Post -a` creates model, migration, factory, seeder, controller, 3 form requests, API resource, policy and a feature test; `Route::apiCrud()` registers every route in one line
- **QueryFlow list endpoints** - pagination (normal / simple / cursor), multi-column sorting, keyword search (also in relations), `filter[...]` shorthand, 25 filter operations with nested groups, `select`, `with`, `with_count`, soft-deleted records, and `count / sum / avg / min / max` aggregates grouped by any column - all **whitelisted per model**
- **Secure by default** - every action goes through a policy; records with a `user_id` can only be changed by their owner; API Resources decide exactly which fields leave the server
- **Bulk delete and restore** - `DELETE /posts` with `ids`, `POST /posts/{id}/restore` for soft-deleted models
- **File uploads** - multipart upload with content-based type check, size limit, random stored names, owner-only access, file removed on delete
- **Security defaults** - rate limits (stricter on auth), CORS locked to your frontend, security headers, strict Eloquent mode, no account enumeration, strong passwords in production
- **API docs** - [Scribe](https://scribe.knuckles.wtf) generates `/docs`, a Postman collection and an OpenAPI spec from the code
- **Quality** - PHPUnit feature tests, Larastan level 6, Pint, GitHub Actions on PHP 8.4 / 8.5

## Requirements

- PHP **8.4+** with `pdo_sqlite` (or MySQL / PostgreSQL), `mbstring`, `fileinfo`
- Composer 2

## Quick start

```bash
git clone https://github.com/brnrajoriya/Laravel-API-Boilerplate.git my-api
cd my-api
composer setup      # install, .env, key, SQLite db, migrate + seed, storage:link, docs
composer dev        # http://localhost:8000
```

- API: `http://localhost:8000/api/v1`
- Docs: `http://localhost:8000/docs`
- Demo account: `demo@example.com` / `Demo@1234` (+ 23 dummy records)

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@example.com","password":"Demo@1234"}'
```

## Scripts

| Command            | What it does                                        |
| ------------------ | --------------------------------------------------- |
| `composer setup`   | First-time setup (see above)                        |
| `composer dev`     | `php artisan serve`                                 |
| `composer test`    | Run the test suite                                  |
| `composer lint`    | Check code style (Pint); `composer format` fixes it |
| `composer analyse` | Static analysis (Larastan)                          |
| `composer docs`    | Regenerate API docs                                 |
| `composer ci`      | lint + analyse + test (what CI runs)                |

## Response format

Every endpoint - success or error - returns the same envelope:

```json
{
  "status": "success",
  "data": { "id": 1, "title": "Hello" },
  "errors": {},
  "hasError": false,
  "message": "Dummy created successfully."
}
```

| Field      | Success                                          | Error                                                     |
| ---------- | ------------------------------------------------ | --------------------------------------------------------- |
| `status`   | `"success"`                                      | `"fail"`                                                  |
| `data`     | object, list, paginator or number; `{}` if empty | `{}`                                                      |
| `errors`   | `{}`                                             | `["message"]`, or `{ "field": ["message"] }` for HTTP 422 |
| `hasError` | `false`                                          | `true`                                                    |
| `message`  | human readable summary (may be `""`)             | first error message                                       |

The HTTP status code is always meaningful too (200, 201, 401, 403, 404, 422, 429, 500).

In controllers:

```php
return $this->resource($post, PostResource::class, 'Post created successfully.', 201); // model(s) → resource
return $this->success(['deleted' => 3]);                                              // plain data
return $this->fail('Not enough credits.', 402);
throw ValidationException::withMessages(['email' => 'Already invited.']);             // → 422 envelope
```

`resource()` accepts a model, a collection, any paginator (the paginator keeps its `current_page`,
`data`, `total`, ... shape) or an aggregate result (returned unchanged).

## Endpoints

All routes are prefixed with `/api/v1`. 🔒 = requires `Authorization: Bearer {token}`.

| Method    | URL                      |    | Description                                                        |
| --------- | ------------------------ | -- | ------------------------------------------------------------------ |
| POST      | `/auth/register`         |    | `{ name, email, password }` → token + user                         |
| POST      | `/auth/login`            |    | `{ email, password, device_name? }` → token + user                 |
| POST      | `/auth/forgot-password`  |    | `{ email }` → emails `FRONTEND_URL/reset-password/{token}?email=`  |
| POST      | `/auth/reset-password`   |    | `{ token, email, password, password_confirmation }`                |
| GET       | `/auth/me`               | 🔒 | Current user                                                       |
| PUT       | `/auth/me`               | 🔒 | `{ name?, email? }`                                                |
| PUT       | `/auth/password`         | 🔒 | `{ current_password, password, password_confirmation }`            |
| POST      | `/auth/logout`           | 🔒 | Revokes the current token                                          |
| GET       | `/uploads`               | 🔒 | My uploads (QueryFlow list parameters)                             |
| POST      | `/uploads`               | 🔒 | `multipart/form-data` with `file`                                  |
| GET       | `/uploads/{id}`          | 🔒 | One of my uploads                                                  |
| DELETE    | `/uploads/{id}`          | 🔒 | Deletes record + stored file                                       |
| GET       | `/dummies`               | 🔒 | Example CRUD resource - list (QueryFlow)                           |
| POST      | `/dummies`               | 🔒 | `{ title, category, description? }`                                |
| DELETE    | `/dummies`               | 🔒 | Bulk delete: `{ ids: [1, 2] }` (max 100, all or nothing)           |
| GET       | `/dummies/{id}`          | 🔒 | Show (`?with=` / `?with_count=` relations)                         |
| PUT/PATCH | `/dummies/{id}`          | 🔒 | Update (partial with PATCH)                                        |
| DELETE    | `/dummies/{id}`          | 🔒 | Delete (soft delete)                                               |
| POST      | `/dummies/{id}/restore`  | 🔒 | Restore a soft-deleted record                                      |

Login / register return:

```json
{ "token": "1|abc...", "token_type": "Bearer", "expires_in": 86400, "expires_at": "2026-09-25T10:00:00+00:00", "user": { "id": 1, "name": "Demo User", "email": "demo@example.com" } }
```

## List endpoints (QueryFlow)

```text
GET /api/v1/dummies?keyword=report&filter[category]=tech&order_by=created_at&order_type=desc&per_page=20
GET /api/v1/dummies?filter[category][]=tech&filter[category][]=news        # IN
GET /api/v1/dummies?pagination=cursor&per_page=50                          # fastest for big tables
GET /api/v1/dummies?return_type=count&group_by=category                    # [{"category":"news","count":4}, ...]
GET /api/v1/dummies?trashed=only                                           # soft-deleted records
```

| Parameter          | Example                              | Notes                                                |
| ------------------ | ------------------------------------ | ---------------------------------------------------- |
| `page`, `per_page` | `2`, `25`                            | default 25, max 100                                  |
| `pagination`       | `paginate` / `simple` / `cursor`     | `simple` skips `COUNT(*)`; `cursor` uses `cursor`    |
| `order_by`         | `title` or `category,title`          | `sortable` columns only                              |
| `order_type`       | `asc` or `desc,asc`                  | default `desc`                                       |
| `keyword`          | `laravel`                            | `searchable` columns, `relation.column` supported    |
| `filter[col]`      | `filter[category]=tech`              | equals; an array means `IN`                          |
| `operations[]`     | see below                            | 25 operations + nested `group` / `or_group`          |
| `select`           | `title,category`                     | key + needed foreign keys are always added           |
| `with`             | `author,comments`                    | `includable` relations                               |
| `with_count`       | `comments`                           | adds `comments_count`                                |
| `trashed`          | `with` / `only`                      | soft-deleting models                                 |
| `return_type`      | `count`, `sum`, `avg`, `min`, `max`  | with `aggregate_column` for sum/avg/min/max          |
| `group_by`         | `category`                           | with an aggregate `return_type`                      |

```js
// Operations (serialize with qs.stringify or equivalent)
{
  operations: [
    { code: 'where', parameters: { column: 'category', operator: '=', value: 'tech' } },
    { code: 'or_group', operations: [                                  // ... OR (title LIKE 'A%' AND description IS NULL)
      { code: 'where', parameters: { column: 'title', operator: 'like', value: 'A%' } },
      { code: 'where_null', parameters: { column: 'description' } },
    ]},
  ],
}
```

Every column / relation must be whitelisted on the model (anything else is a 422), and client filters
are wrapped in one group, so they can never escape constraints you add in the controller. The full list
of operations and options is in the [QueryFlow README](https://github.com/brnrajoriya/laravel-queryflow#readme).
The original `addOperationsInQuery($query, $operations)` helper keeps working.

### Whitelisting on a model

```php
#[Queryable(
    filterable: ['id', 'status', 'author_id', 'published_at'], // filter / operations / select / group_by / aggregates
    sortable:   ['id', 'title', 'published_at'],              // order_by (default: filterable)
    searchable: ['title', 'body', 'author.name'],             // keyword
    includable: ['author', 'tags'],                           // with / with_count / where_has
)]
class Post extends Model
{
    use HasQueryFlow, SoftDeletes;
}
```

Without `filterable`, the id, fillable columns and timestamps are allowed, minus `$hidden` ones.

## Generating a new resource

```bash
php artisan make:model Post -a
# or only the API layer for an existing model (the model is inferred from the name):
php artisan make:controller Api/V1/PostController --api
```

This creates (existing files are never overwritten):

```
app/Models/Post.php                            # #[Queryable] + HasQueryFlow
app/Http/Controllers/Api/V1/PostController.php # index, store, show, update, destroy, bulkDestroy (+ restore)
app/Http/Requests/Post/IndexRequest.php        # extends QueryFlowRequest
app/Http/Requests/Post/StoreRequest.php
app/Http/Requests/Post/UpdateRequest.php
app/Http/Resources/PostResource.php            # the JSON shape of a post
app/Policies/PostPolicy.php                    # who may do what
tests/Feature/Api/PostApiTest.php              # starter tests
database/migrations|factories|seeders/...      # with -a
```

The generated controller:

```php
/**
 * @group Posts
 *
 * This API allows you to manage posts.
 */
class PostController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Post::class);

        // Add your own constraints, e.g. ->where('user_id', $request->user()->id).
        $result = QueryFlow::for(Post::query())
            ->apply($request->queryFlow())
            ->get();

        return $this->resource($result, PostResource::class);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        Gate::authorize('create', Post::class);

        $post = Post::create($request->validated());

        return $this->resource($post, PostResource::class, 'Post created successfully.', 201);
    }

    // show(), update(), destroy(), bulkDestroy() - and restore() when the model uses SoftDeletes
}
```

Then:

1. Add columns to the migration, and `#[Fillable([...])]` + the QueryFlow whitelists to the model.
2. Add rules to `StoreRequest` / `UpdateRequest` (use `sometimes` in update so PATCH works).
3. List the exposed fields in `PostResource` (add `whenLoaded` relations and `whenCounted` counts).
4. Register the routes inside the `auth:sanctum` group in `routes/api.php` (the command prints the line):
   ```php
   Route::apiCrud('posts', PostController::class);
   ```
5. Adjust `PostPolicy` if the defaults do not fit, and extend `PostApiTest`.

Customise the templates in `stubs/api/*.stub` and `stubs/model.stub`. Invokable, singleton and nested
controllers keep Laravel's default behaviour.

## Authorization defaults

Generated policies (`app/Policies/{Model}Policy.php`) start with:

| Ability                      | Rule                                                                     |
| ---------------------------- | ------------------------------------------------------------------------ |
| `viewAny`, `view`, `create`  | any authenticated user                                                   |
| `update`, `delete`, `restore`| the owner, when the record has a `user_id`; otherwise any authenticated user |

For owned resources, set the owner when creating (`$request->user()->posts()->create(...)`) and scope
the list in `index` (`QueryFlow::for($request->user()->posts())`) - see `UploadController`. Replace any
rule with roles, teams or admin checks as needed.

## Using it with the Angular Boilerplate

The [Angular Boilerplate](https://github.com/brnrajoriya/Angular-Boilerplate) talks to this API
out of the box: same auth endpoints, response envelope, QueryFlow parameters and demo account.

```bash
# this API
composer setup && composer dev                  # http://localhost:8000

# the frontend (in its own folder)
# set useMockApi: false in src/environments/environment.development.ts
npm start                                       # http://localhost:4200 - /api is proxied to :8000
```

In production, set `FRONTEND_URL` (password reset links go to `FRONTEND_URL/reset-password/{token}?email=...`)
and `CORS_ALLOWED_ORIGINS` to the frontend's URL. All backend specifics on the Angular side live in
`src/app/core/api/`, so either project can be swapped for another.

## Configuration

`.env` highlights (see `.env.example`, `config/api.php` and `config/queryflow.php`):

| Variable                | Default                                  | Purpose                                          |
| ----------------------- | ---------------------------------------- | ------------------------------------------------ |
| `FRONTEND_URL`          | `http://localhost:4200`                  | Reset-password links, default CORS origin        |
| `CORS_ALLOWED_ORIGINS`  | `FRONTEND_URL`                           | Comma separated allowed browser origins          |
| `API_TOKEN_TTL`         | `1440` (minutes)                         | Access token lifetime                            |
| `API_RATE_LIMIT`        | `60` / min                               | Per user (or IP)                                 |
| `API_AUTH_RATE_LIMIT`   | `5` / min                                | Login, register, password reset per email + IP   |
| `API_UPLOAD_RATE_LIMIT` | `20` / min                               | Uploads per user                                 |
| `UPLOADS_DISK`          | `public`                                 | Any filesystem disk (e.g. `s3`)                  |
| `UPLOADS_MAX_KB`        | `10240`                                  | Max upload size                                  |
| `UPLOADS_MIMES`         | `jpg,jpeg,png,gif,webp,mp4,webm,mov,pdf` | Allowed types (SVG is excluded on purpose)       |

QueryFlow limits and parameter names: `php artisan vendor:publish --tag=queryflow-config`.

Database: SQLite works out of the box. For MySQL / PostgreSQL set `DB_CONNECTION`, `DB_HOST`, ... in `.env`.

## Project structure

```
app/
├── Console/Commands/                    # make:controller / make:model overrides (API generator)
├── Enums/                               # backed enums used in casts + validation
├── Exceptions/ApiExceptionRenderer.php  # every exception → envelope
├── Http/
│   ├── Controllers/Api/V1/              # versioned API controllers
│   ├── Controllers/Controller.php       # success() / resource() / fail()
│   ├── Middleware/                      # ForceJsonResponse, SecurityHeaders
│   ├── Requests/                        # BulkDestroyRequest + one folder per resource
│   ├── Resources/                       # API resources (JSON shape of each model)
│   └── Responses/ApiResponse.php        # the envelope
├── Policies/                            # one policy per resource + Concerns/ChecksOwnership
└── Providers/ApiRouteServiceProvider.php  # Route::apiCrud()
config/api.php                           # rate limits, token TTL, uploads
stubs/                                   # generator templates
```

## Best practices baked in (and to keep following)

- **Validate with Form Requests** and save only `$request->validated()` - never `$request->all()`.
- **Return API Resources**, not raw models, so the database schema is never your API contract.
- **Authorize every action** with a policy (`Gate::authorize(...)`), as the generated controllers do.
- **Whitelist, don't blacklist**: models only expose what their `#[Queryable]` lists.
- **Index what you filter**: add database indexes for the `filterable` / `sortable` columns clients actually use.
- **Use cursor pagination** (`pagination=cursor`) for large tables, and `with_count` when a number is enough.
- **Avoid N+1 queries**: strict mode throws on lazy loading outside production - eager load with `with`.
- **Version the API** (`/api/v1`); add `/api/v2` controllers instead of breaking clients.
- **Use enums** for fixed values (`DummyCategory`) - cast on the model, `Rule::enum()` in requests.
- **Keep controllers thin**: move multi-step business logic into action / service classes.
- **Queue slow work** (emails, image processing) and run `php artisan queue:work`.
- **Write a feature test per endpoint** - the generator gives you a starting point.

## Security

- Sanctum tokens are hashed in the database, expire (`API_TOKEN_TTL`) and are pruned daily; logout revokes the current token, password change / reset revoke the others.
- Login does the same work for unknown emails (no timing leak); forgot-password answers the same for any email (no account enumeration).
- Rate limits on everything, stricter on auth; `429` responses include `Retry-After`.
- Uploads: type checked from the file **content**, random stored names, size limit, no SVG, owner-only access.
- QueryFlow: whitelisted columns / relations / operators only, values always bound, limits on page size, operations, nesting and relations.
- CORS only for configured origins; `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Content-Security-Policy`, and HSTS over HTTPS.
- Production: HTTPS URLs forced, destructive DB commands blocked, passwords must be strong and not found in known data breaches.

## Deployment checklist

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize          # caches config, routes, events and views
```

- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL`, `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS`, database and mail settings.
- Cron: `* * * * * php /path/to/artisan schedule:run` (prunes expired tokens and password-reset tokens).
- Queue worker if you queue jobs: `php artisan queue:work --tries=3` (Supervisor / systemd).
- Serve over HTTPS with the web server root pointing to `public/`.
- Works on Laravel Cloud, Forge, Docker (Laravel Sail) or any PHP 8.4+ host.

## Testing

```bash
composer test
```

Tests run on an in-memory SQLite database and cover auth, the response envelope, CRUD, QueryFlow
parameters, policies, bulk delete / restore, uploads, rate limiting, CORS and the generators.
QueryFlow itself has its own test suite (SQLite, MySQL and PostgreSQL).

## Credits & license

Created by **Bhaskar Rajoriya** ([LinkedIn](https://www.linkedin.com/in/brnrajoriya/)).
QueryFlow - the list-endpoint engine used here - is also by Bhaskar Rajoriya:
[brnrajoriya/laravel-queryflow](https://github.com/brnrajoriya/laravel-queryflow).

Released under the [MIT License](LICENSE). Please keep the copyright notice; see [CITATION.cff](CITATION.cff)
for citing this project.
