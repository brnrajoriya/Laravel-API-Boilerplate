<?php

namespace Tests\Feature;

use App\Models\Concerns\HasApiQuery;
use App\Models\Dummy;
use App\Support\QueryOperations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QueryOperationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function operations(): array
    {
        $v = ['column' => 'title', 'operator' => '=', 'value' => 'x'];

        return [
            'where' => [['code' => 'where', 'parameters' => $v], '"title" = \'x\''],
            'or_where' => [['code' => 'or_where', 'parameters' => $v], '"title" = \'x\''],
            'where_date' => [['code' => 'where_date', 'parameters' => ['column' => 'created_at', 'operator' => '>=', 'value' => '2026-01-01']], 'strftime(\'%Y-%m-%d\', "created_at")'],
            'where_month' => [['code' => 'where_month', 'parameters' => ['column' => 'created_at', 'operator' => '=', 'value' => 5]], 'strftime(\'%m\', "created_at")'],
            'where_day' => [['code' => 'where_day', 'parameters' => ['column' => 'created_at', 'operator' => '=', 'value' => 5]], 'strftime(\'%d\', "created_at")'],
            'where_year' => [['code' => 'where_year', 'parameters' => ['column' => 'created_at', 'operator' => '=', 'value' => 2026]], 'strftime(\'%Y\', "created_at")'],
            'where_time' => [['code' => 'where_time', 'parameters' => ['column' => 'created_at', 'operator' => '<', 'value' => '12:00']], 'strftime(\'%H:%M:%S\', "created_at")'],
            'where_in' => [['code' => 'where_in', 'parameters' => ['column' => 'id', 'values' => [1, 2]]], '"id" in (1, 2)'],
            'where_not_in' => [['code' => 'where_not_in', 'parameters' => ['column' => 'id', 'values' => [1]]], '"id" not in (1)'],
            'or_where_in' => [['code' => 'or_where_in', 'parameters' => ['column' => 'id', 'values' => [1]]], '"id" in (1)'],
            'or_where_not_in' => [['code' => 'or_where_not_in', 'parameters' => ['column' => 'id', 'values' => [1]]], '"id" not in (1)'],
            'where_between' => [['code' => 'where_between', 'parameters' => ['column' => 'id', 'values' => [1, 5]]], '"id" between 1 and 5'],
            'or_where_between' => [['code' => 'or_where_between', 'parameters' => ['column' => 'id', 'values' => [1, 5]]], '"id" between 1 and 5'],
            'where_not_between' => [['code' => 'where_not_between', 'parameters' => ['column' => 'id', 'values' => [1, 5]]], '"id" not between 1 and 5'],
            'or_where_not_between' => [['code' => 'or_where_not_between', 'parameters' => ['column' => 'id', 'values' => [1, 5]]], '"id" not between 1 and 5'],
            'where_null' => [['code' => 'where_null', 'parameters' => ['column' => 'description']], '"description" is null'],
            'where_not_null' => [['code' => 'where_not_null', 'parameters' => ['column' => 'description']], '"description" is not null'],
            'or_where_null' => [['code' => 'or_where_null', 'parameters' => ['column' => 'description']], '"description" is null'],
            'or_where_not_null' => [['code' => 'or_where_not_null', 'parameters' => ['column' => 'description']], '"description" is not null'],
            'where_column' => [['code' => 'where_column', 'parameters' => ['column_1' => 'created_at', 'operator' => '<', 'column_2' => 'updated_at']], '"created_at" < "updated_at"'],
            'or_where_column' => [['code' => 'or_where_column', 'parameters' => ['column_1' => 'created_at', 'operator' => '=', 'column_2' => 'updated_at']], '"created_at" = "updated_at"'],
            'having' => [['code' => 'having', 'parameters' => ['column' => 'category', 'operator' => '!=', 'value' => 'news']], 'having "category" != \'news\''],
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    #[DataProvider('operations')]
    public function test_every_operation_builds_the_expected_sql(array $operation, string $expected): void
    {
        $sql = QueryOperations::apply(Dummy::query(), [$operation])->toRawSql();

        $this->assertStringContainsString($expected, $sql);
        $this->assertCount(24, QueryOperations::codes());
    }

    public function test_operations_are_grouped_so_or_where_cannot_escape(): void
    {
        $sql = Dummy::query()->where('category', 'tech')->applyOperations([
            ['code' => 'where', 'parameters' => ['column' => 'id', 'operator' => '>', 'value' => 1]],
            ['code' => 'or_where', 'parameters' => ['column' => 'id', 'operator' => '<', 'value' => 0]],
        ])->toRawSql();

        $this->assertStringContainsString('where "category" = \'tech\' and ("id" > 1 or "id" < 0)', $sql);
    }

    public function test_relations_must_be_whitelisted_and_their_columns_too(): void
    {
        Schema::create('posts', fn (Blueprint $t) => $t->id());
        Schema::create('comments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('post_id');
            $t->string('body');
            $t->string('secret')->nullable();
        });

        $post = PostFixture::query()->create();
        $post->comments()->create(['body' => 'hello']);
        PostFixture::query()->create();

        $has = QueryOperations::apply(PostFixture::query(), [['code' => 'has', 'relation' => 'comments']]);
        $this->assertSame(1, $has->count());

        $whereHas = QueryOperations::apply(PostFixture::query(), [[
            'code' => 'where_has',
            'relation' => 'comments',
            'parameters' => ['column' => 'body', 'operator' => 'like', 'value' => 'hel%'],
        ]]);
        $this->assertSame(1, $whereHas->count());

        $this->assertInvalid(fn () => QueryOperations::apply(PostFixture::query(), [[
            'code' => 'where_has', 'relation' => 'comments',
            'parameters' => ['column' => 'secret', 'operator' => '=', 'value' => 'x'],
        ]]));
        $this->assertInvalid(fn () => QueryOperations::apply(PostFixture::query(), [['code' => 'has', 'relation' => 'delete']]));
    }

    public function test_malformed_parameters_are_rejected(): void
    {
        $bad = [
            ['code' => 'where_in', 'parameters' => ['column' => 'id', 'values' => []]],
            ['code' => 'where_between', 'parameters' => ['column' => 'id', 'values' => [1]]],
            ['code' => 'where', 'parameters' => ['column' => 'id', 'operator' => '=', 'value' => ['array']]],
            ['code' => 'where', 'parameters' => ['column' => ['id'], 'operator' => '=', 'value' => 1]],
            ['code' => 'nope'],
        ];

        foreach ($bad as $operation) {
            $this->assertInvalid(fn () => QueryOperations::apply(Dummy::query(), [$operation]));
        }
    }

    public function test_global_helper_is_kept_for_compatibility(): void
    {
        $sql = addOperationsInQuery(Dummy::query(), [['code' => 'where_null', 'parameters' => ['column' => 'description']]])->toRawSql();

        $this->assertStringContainsString('"description" is null', $sql);
    }

    private function assertInvalid(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}

class PostFixture extends Model
{
    use HasApiQuery;

    protected $table = 'posts';

    public $timestamps = false;

    /** @var list<string> */
    protected array $includable = ['comments'];

    /**
     * @return HasMany<CommentFixture, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CommentFixture::class, 'post_id');
    }
}

class CommentFixture extends Model
{
    protected $table = 'comments';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['body'];

    /** @var list<string> */
    protected array $filterable = ['id', 'body'];
}
