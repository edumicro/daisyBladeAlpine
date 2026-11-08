<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\Paginator;

/*
 * The answers that the components read are the ones Laravel produces, nothing else. These tests make the answers with the
 * REAL classes of Laravel (paginators, JsonResource, ResourceCollection, an Eloquent model) and read them with the very
 * `answerShapes` of http.js (through node), so the contract cannot drift from what a Laravel app really returns.
 */

class AnswerShapeModel extends Model
{
    protected $guarded = [];

    protected $casts = ['data' => 'array'];
}

class AnswerShapeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}

/** What Laravel sends for `$answer`: the body of the HTTP response, decoded. */
function laravelBodyOf(mixed $answer): string
{
    $response = $answer instanceof JsonResource || $answer instanceof ResourceCollection
        ? $answer->toResponse(request())
        : response()->json($answer);

    return $response->getContent();
}

/** What a component would get from `$body` through `answerShapes[$shape]`: ['result' => ...] or ['error' => reason]. */
function componentReads(string $shape, string $body): array
{
    $process = proc_open(['node', '--no-warnings', dirname(__DIR__).'/js/helpers/read-answer.mjs', $shape], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
    fwrite($pipes[0], $body);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    proc_close($process);

    return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
}

/** Models for the answers, not saved anywhere. */
function someModels(int $count = 3): Illuminate\Support\Collection
{
    return collect(range(1, $count))->map(fn ($id) => new AnswerShapeModel(['id' => $id, 'name' => "Name {$id}", 'data' => ['color' => 'red']]));
}

it('table: a paginate() returned as it is keeps its pagination', function () {
    $paginator = new LengthAwarePaginator(someModels(2), 5, 2, 2, ['path' => '/rows']);

    $read = componentReads('tableRows', laravelBodyOf($paginator));

    expect($read['result']['rows'])->toHaveCount(2)
        ->and($read['result']['meta']['last_page'])->toBe(3)
        ->and($read['result']['meta']['total'])->toBe(5)
        ->and($read['result']['meta']['current_page'])->toBe(2);
});

it('table: a ResourceCollection of a paginator keeps its meta', function () {
    $paginator = new LengthAwarePaginator(someModels(2), 5, 2, 1, ['path' => '/rows']);

    $read = componentReads('tableRows', laravelBodyOf(AnswerShapeResource::collection($paginator)));

    expect($read['result']['rows'])->toHaveCount(2)
        ->and($read['result']['meta']['total'])->toBe(5);
});

it('table: a simplePaginate() returned as it is is a table without totals', function () {
    $paginator = new Paginator(someModels(2), 2, 1, ['path' => '/rows']);

    $read = componentReads('tableRows', laravelBodyOf($paginator));

    expect($read['result']['rows'])->toHaveCount(2)
        ->and($read['result']['meta']['current_page'])->toBe(1);
});

afterEach(fn () => JsonResource::wrap('data'));

/** Three pages of two rows, as the paginators of Laravel build them: page 1 is `$page = 1`. */
function pagerOfPaginator(string $kind, int $page, bool $asResource = false): array
{
    $paginator = match ($kind) {
        'paginate' => new LengthAwarePaginator(someModels(2), 6, 2, $page, ['path' => '/rows']),
        // simplePaginate() reads one row more than the page size to know whether a next page exists.
        'simplePaginate' => (new Paginator(someModels($page < 3 ? 3 : 2), 2, $page, ['path' => '/rows']))->hasMorePagesWhen($page < 3),
        // cursorPaginate(): the cursor of the neighbours, not their number.
        'cursorPaginate' => new CursorPaginator(someModels($page < 3 ? 3 : 2), 2, $page === 1 ? null : new Cursor(['id' => 2], true), ['path' => '/rows']),
    };

    return componentReads('tableRows', laravelBodyOf($asResource ? AnswerShapeResource::collection($paginator) : $paginator))['result']['pager'];
}

it('table: the arrows lead to the previous and the next page of every paginator of Laravel, as it is or inside a resource', function (string $kind, bool $asResource) {
    $first = pagerOfPaginator($kind, 1, $asResource);
    $middle = pagerOfPaginator($kind, 2, $asResource);
    $last = pagerOfPaginator($kind, 3, $asResource);

    expect($first['previous'])->toBeNull()->and($first['next'])->not->toBeNull()
        ->and($middle['previous'])->not->toBeNull()->and($middle['next'])->not->toBeNull()
        ->and($last['next'])->toBeNull();
})->with(['paginate', 'simplePaginate', 'cursorPaginate'])->with([false, true]);

it('table: a numbered paginator points at page numbers and a cursor one at cursors', function () {
    expect(pagerOfPaginator('paginate', 2)['next'])->toBe(['page' => 3])
        ->and(pagerOfPaginator('simplePaginate', 2, true)['previous'])->toBe(['page' => 1])
        ->and(pagerOfPaginator('cursorPaginate', 1)['next'])->toHaveKey('cursor');
});

it('table: ->additional([...]) on a resource collection is still a list', function () {
    $paginator = new LengthAwarePaginator(someModels(2), 5, 2, 1, ['path' => '/rows']);

    $read = componentReads('tableRows', laravelBodyOf(AnswerShapeResource::collection($paginator)->additional(['success' => true])));

    expect($read['result']['rows'])->toHaveCount(2)->and($read['result']['meta']['total'])->toBe(5);
});

it('table: a resource collection under JsonResource::withoutWrapping() is a bare array, which is not a table answer', function () {
    JsonResource::withoutWrapping();
    $paginator = new LengthAwarePaginator(someModels(2), 5, 2, 1, ['path' => '/rows']);

    // With a paginator Laravel keeps `data` even when it does not wrap (it has `links` and `meta` to carry).
    $read = componentReads('tableRows', laravelBodyOf(AnswerShapeResource::collection($paginator)));

    expect($read['result']['rows'])->toHaveCount(2);
});

it('lists (calendar): additional([...]) does not make a list invalid', function () {
    $events = collect([['id' => 1, 'title' => 'Ev', 'start' => '2026-05-15']]);

    expect(componentReads('calendarEvents', json_encode(['data' => $events, 'success' => true]))['result'])->toHaveCount(1);
});

it('select options: `pluck()` gives the map and `->values()` the list of rows', function () {
    $people = collect([['id' => 5, 'name' => 'Five'], ['id' => 6, 'name' => 'Six']]);

    expect(componentReads('selectOptions', laravelBodyOf($people->pluck('name', 'id')))['result'])->toHaveCount(2)
        ->and(componentReads('selectOptions', laravelBodyOf($people->map(fn ($p) => ['value' => $p['id'], 'label' => $p['name']])->values()))['result'])->toHaveCount(2);
});

it('table: a ResourceCollection without pagination is a table with an empty meta', function () {
    $read = componentReads('tableRows', laravelBodyOf(AnswerShapeResource::collection(someModels(2))));

    expect($read['result']['rows'])->toHaveCount(2)
        ->and($read['result']['meta'])->toBe([]);
});

it('table: a bare list is not a table answer', function () {
    expect(componentReads('tableRows', laravelBodyOf(someModels(2)))['error'])->toBe('shape');
});

it('select options: a JsonResource collection and a paginator are wrapped in `data`: not understood, and process-results unwraps them', function () {
    $options = collect([['value' => 1, 'label' => 'One'], ['value' => 2, 'label' => 'Two']]);
    $paginated = new LengthAwarePaginator($options, 2, 10, 1, ['path' => '/o']);

    expect(componentReads('selectOptions', laravelBodyOf($options))['result'])->toHaveCount(2)
        ->and(componentReads('selectOptions', laravelBodyOf($paginated))['error'])->toBe('shape')
        ->and(componentReads('selectOptions', laravelBodyOf(AnswerShapeResource::collection(someModels(2))))['error'])->toBe('shape')
        ->and(componentReads('selectOptions', json_encode(['results' => $options]))['error'])->toBe('shape');
});

it('detail: a JsonResource is unwrapped from its data', function () {
    $read = componentReads('resource', laravelBodyOf(new AnswerShapeResource(someModels(1)->first())));

    expect($read['result'])->toBe(['id' => 1, 'name' => 'Name 1']);
});

it('detail: ->additional([...]) on a resource is unwrapped too, and its extra keys are not the record', function () {
    $read = componentReads('resource', laravelBodyOf((new AnswerShapeResource(someModels(1)->first()))->additional(['success' => true])));

    expect($read['result'])->toBe(['id' => 1, 'name' => 'Name 1']);
});

it('detail: the rule is single, a `data` that is an object is a resource, so a model with a `data` column must be wrapped by the controller', function () {
    $model = someModels(1)->first();

    $asItIs = componentReads('resource', laravelBodyOf($model))['result'];
    $wrapped = componentReads('resource', laravelBodyOf(['data' => $model]))['result'];

    // As it is, the envelope rule removes the model's own `data` (docs/contrato.md says so); wrapped by hand nothing is lost.
    // (A JsonResource does not help here: Laravel does not wrap an array that already has a `data` key.)
    expect($asItIs)->toBe(['color' => 'red'])
        ->and($wrapped['name'])->toBe('Name 1')
        ->and($wrapped['data'])->toBe(['color' => 'red']);
});

it('detail: a list of models is not a detail', function () {
    expect(componentReads('resource', laravelBodyOf(someModels(2)))['error'])->toBe('shape');
});
