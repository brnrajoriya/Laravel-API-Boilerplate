# Laravel API Boilerplate

[![CI](https://github.com/brnrajoriya/Laravel-API-Boilerplate/actions/workflows/ci.yml/badge.svg)](https://github.com/brnrajoriya/Laravel-API-Boilerplate/actions/workflows/ci.yml)

A production-ready **Laravel 13** REST API starter with the configuration every project needs already
done: token authentication, file uploads, a CRUD generator, safe dynamic filtering, one JSON response
format, rate limiting, API docs, tests and CI.

It pairs with the [Angular Boilerplate](https://github.com/brnrajoriya/Angular-Boilerplate) frontend
(same demo account, same resource examples), but it is a complete project on its own.

By [Bhaskar Rajoriya](https://www.linkedin.com/in/brnrajoriya/).

---

## Features

- **Auth with Laravel Sanctum** - register, login, logout, me, update profile, change password, forgot / reset password (link to your frontend); expiring tokens, one per device
- **One response envelope** for every success and error: `{ status, data, errors, hasError, message }`
- **Global error handling** - validation (422), 401, 403, 404, 405, 429 and 500 all use the envelope; no `try/catch` in controllers; internal details hidden in production
- **CRUD generator** - `php artisan make:model Post -a` or `make:controller PostController --api` creates a complete API controller + `Requests/Post/{Index,Store,Update}Request`
- **Dynamic list queries** - pagination, sorting, keyword search, `select`, `with`, `group_by`, `count` and 24 filter `operations`, all **whitelisted per model**
- **File uploads** - multipart upload with content-based type check, size limit, random stored names, owner-only access, file removed on delete
- **Security defaults** - rate limits (stricter on auth), CORS locked to your frontend, security headers, strict Eloquent mode, no account enumeration, strong passwords in production
- **API docs** - [Scribe](https://scribe.knuckles.wtf) generates `/docs`, a Postman collection and an OpenAPI spec from the code
- **Quality** - 60+ feature tests (PHPUnit), Larastan level 6, Pint, GitHub Actions on PHP 8.4 / 8.5

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
return $this->success($post, 'Post created successfully.', 201);
return $this->fail('Not enough credits.', 402);
throw ValidationException::withMessages(['email' => 'Already invited.']); // → 422 envelope
```

## Endpoints

All routes are prefixed with `/api/v1`. 🔒 = requires `Authorization: Bearer {token}`.

| Method    | URL                     |    | Description                                                        |
| --------- | ----------------------- | -- | ------------------------------------------------------------------ |
| POST      | `/auth/register`        |    | `{ name, email, password }` → token + user                         |
| POST      | `/auth/login`           |    | `{ email, password, device_name? }` → token + user                 |
| POST      | `/auth/forgot-password` |    | `{ email }` → emails `FRONTEND_URL/reset-password/{token}?email=`  |
| POST      | `/auth/reset-password`  |    | `{ token, email, password, password_confirmation }`                |
| GET       | `/auth/me`              | 🔒 | Current user                                                       |
| PUT       | `/auth/me`              | 🔒 | `{ name?, email? }`                                                |
| PUT       | `/auth/password`        | 🔒 | `{ current_password, password, password_confirmation }`            |
| POST      | `/auth/logout`          | 🔒 | Revokes the current token                                          |
| GET       | `/uploads`              | 🔒 | My uploads (list parameters below)                                 |
| POST      | `/uploads`              | 🔒 | `multipart/form-data` with `file`                                  |
| GET       | `/uploads/{id}`         | 🔒 | One of my uploads                                                  |
| DELETE    | `/uploads/{id}`         | 🔒 | Deletes record + stored file                                       |
| GET       | `/dummies`              | 🔒 | Example CRUD resource - list                                       |
| POST      | `/dummies`              | 🔒 | `{ title, category, description? }`                                |
| GET       | `/dummies/{id}`         | 🔒 | Show (`?with=` relations)                                          |
| PUT/PATCH | `/dummies/{id}`         | 🔒 | Update (partial with PATCH)                                        |
| DELETE    | `/dummies/{id}`         | 🔒 | Delete                                                             |

Login / register return:

```json
{ "token": "1|abc...", "token_type": "Bearer", "expires_in": 86400, "expires_at": "2026-09-25T10:00:00+00:00", "user": { "id": 1, "name": "Demo User", "email": "demo@example.com" } }
```

## List endpoints: paging, sorting, search and filters

Every generated `index` accepts:

| Parameter     | Example                      | Notes                                            |
| ------------- | ---------------------------- | ------------------------------------------------ |
| `page`        | `2`                          |                                                  |
| `per_page`    | `25`                         | 1 - 100, default 25                              |
| `order_by`    | `created_at`                 | must be in the model's `$sortable`, default `id` |
| `order_type`  | `asc` / `desc`               | default `desc`                                   |
| `keyword`     | `laravel`                    | case-insensitive search in `$searchable` columns |
| `select[]`    | `select[]=id&select[]=title` | must be in `$filterable`; `id` is always added   |
| `with`        | `author,tags`                | must be in `$includable`                         |
| `group_by`    | `category`                   | must be in `$filterable`                         |
| `return_type` | `count`                      | returns just the number                          |
| `operations`  | see below                    | up to 25 filters                                 |

`data` of a list is Laravel's paginator: `{ current_page, data: [...], per_page, total, last_page, from, to, links, ... }`.

### Filter operations

```text
GET /api/v1/dummies
  ?operations[0][code]=where&operations[0][parameters][column]=category&operations[0][parameters][operator]==&operations[0][parameters][value]=tech
  &operations[1][code]=where_between&operations[1][parameters][column]=created_at&operations[1][parameters][values][]=2026-01-01&operations[1][parameters][values][]=2026-12-31
```

The same filters as a JavaScript object (serialize with `qs.stringify` or equivalent):

```js
const params = {
  operations: [
    { code: 'where_in', parameters: { column: 'category', values: ['tech', 'news'] } },
    { code: 'where_has', relation: 'author', parameters: { column: 'name', operator: 'like', value: 'Jo%' } },
  ],
};
```

| Parameters                                    | Codes                                                                                              |
| --------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `column`, `operator`, `value`                 | `where`, `or_where`, `where_date`, `where_month`, `where_day`, `where_year`, `where_time`, `having` |
| `column`, `values[]`                          | `where_in`, `where_not_in`, `or_where_in`, `or_where_not_in`                                       |
| `column`, `values[2]`                         | `where_between`, `or_where_between`, `where_not_between`, `or_where_not_between`                   |
| `column`                                      | `where_null`, `where_not_null`, `or_where_null`, `or_where_not_null`                               |
| `column_1`, `operator`, `column_2`            | `where_column`, `or_where_column`                                                                  |
| `relation` (+ optional column/operator/value) | `where_has`, `has`                                                                                 |

Operators: `=`, `!=`, `<>`, `<`, `<=`, `>`, `>=`, `like`, `not like`.

**Safety rules** (the part most "dynamic filter" helpers get wrong):

- Only whitelisted columns and relations are accepted; anything else is a 422, never raw SQL.
- All operations are wrapped in **one group**: `... AND (op1 OR op2)`. An `or_where` can never escape constraints you add in the controller (e.g. `where('user_id', $me)`).
- Values must be scalars; unknown codes and malformed parameters are rejected.

The old global helper still works: `addOperationsInQuery($query, $operations)`. Prefer the `->applyOperations()` scope.

### Whitelisting columns on a model

```php
class Post extends Model
{
    use HasApiQuery;

    protected array $filterable = ['id', 'status', 'author_id', 'published_at']; // filter / select / group_by
    protected array $sortable   = ['id', 'title', 'published_at'];              // order_by (default: filterable)
    protected array $searchable = ['title', 'body'];                            // keyword
    protected array $includable = ['author', 'tags'];                           // with / where_has / has
}
```

Without `$filterable`, the id, fillable columns and timestamps are allowed, minus `$hidden` ones.

## Generating a new resource

```bash
php artisan make:model Post -a        # model, migration, factory, seeder, policy, controller, requests
# or just the controller (the model is inferred from the name):
php artisan make:controller Api/V1/PostController --api
php artisan make:controller Api/V1/PostController --model=Post
```

This creates:

```
app/Models/Post.php                          # uses HasApiQuery, whitelist placeholders
app/Http/Controllers/Api/V1/PostController.php
app/Http/Requests/Post/IndexRequest.php      # extends ApiIndexRequest (all list parameters)
app/Http/Requests/Post/StoreRequest.php
app/Http/Requests/Post/UpdateRequest.php
```

The generated controller is ready to use:

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
        $query = Post::query()
            ->apiSelect($request->selectColumns())
            ->apiWith($request->relations())
            ->search($request->keyword())
            ->applyOperations($request->operations())
            ->apiGroupBy($request->groupBy())
            ->apiOrderBy($request->orderBy(), $request->orderType());

        if ($request->wantsCount()) {
            return $this->success($query->count());
        }

        return $this->success($query->paginate($request->perPage())->withQueryString());
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $post = Post::create($request->validated());

        return $this->success($post, 'Post created successfully.', 201);
    }

    // show(), update(), destroy() ...
}
```

Then:

1. Add columns to the migration, and `#[Fillable([...])]` + whitelists to the model.
2. Add rules to `StoreRequest` / `UpdateRequest` (use `sometimes` in update so PATCH works).
3. Register the route (the command prints it) inside the `auth:sanctum` group in `routes/api.php`:
   `Route::apiResource('posts', PostController::class);`
4. Need per-user data? Scope the query in `index` (`->where('user_id', $request->user()->id)`) and add a
   policy - see `UploadPolicy` and `Gate::authorize()` in `UploadController`.

Customise the templates in `stubs/api/*.stub` and `stubs/model.stub`. Invokable, singleton and nested
controllers keep Laravel's default behaviour.

## Configuration

`.env` highlights (see `.env.example` and `config/api.php`):

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

Database: SQLite works out of the box. For MySQL / PostgreSQL set `DB_CONNECTION`, `DB_HOST`, ... in `.env`.

## Project structure

```
app/
├── Console/Commands/                  # make:controller / make:model overrides (API generator)
├── Enums/                             # backed enums used in casts + validation
├── Exceptions/ApiExceptionRenderer.php  # every exception → envelope
├── Http/
│   ├── Controllers/Api/V1/            # versioned API controllers
│   ├── Controllers/Controller.php     # success() / fail() / relations()
│   ├── Middleware/                    # ForceJsonResponse, SecurityHeaders
│   ├── Requests/                      # ApiIndexRequest + one folder per resource
│   └── Responses/ApiResponse.php      # the envelope
├── Models/Concerns/HasApiQuery.php    # apiSelect, apiWith, search, applyOperations, ...
├── Policies/
├── Support/                           # QueryOperations, ApiQueryColumns
└── helpers.php                        # addOperationsInQuery()
config/api.php                         # rate limits, token TTL, uploads
stubs/                                 # generator templates
```

## Best practices baked in (and to keep following)

- **Validate with Form Requests** and save only `$request->validated()` - never `$request->all()`.
- **Never expose internals**: keep `APP_DEBUG=false` in production; the renderer then hides exception messages.
- **Authorize with policies** (`Gate::authorize('update', $post)`), not `if` checks spread through controllers.
- **Whitelist, don't blacklist**: new models only expose what you list in `$filterable` / `$includable`.
- **Avoid N+1 queries**: strict mode throws on lazy loading outside production - eager load with `with` / `apiWith`.
- **Version the API** (`/api/v1`); add `/api/v2` controllers instead of breaking clients.
- **Use enums** for fixed values (`DummyCategory`) - cast on the model, `Rule::enum()` in requests.
- **Keep controllers thin**: move multi-step business logic into action / service classes.
- **Queue slow work** (emails, image processing) and run `php artisan queue:work`.
- **Write a feature test per endpoint** - see `tests/Feature/Api` for the pattern.

## Security

- Sanctum tokens are hashed in the database, expire (`API_TOKEN_TTL`) and are pruned daily; logout revokes the current token, password change / reset revoke the others.
- Login does the same work for unknown emails (no timing leak); forgot-password answers the same for any email (no account enumeration).
- Rate limits on everything, stricter on auth; `429` responses include `Retry-After`.
- Uploads: type checked from the file **content**, random stored names, size limit, no SVG, owner-only access.
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

Tests run on an in-memory SQLite database and cover auth, the response envelope, CRUD, every filter
operation, the injection / escape protections, uploads, rate limiting, CORS and the generators.

## License

MIT © Bhaskar Rajoriya
