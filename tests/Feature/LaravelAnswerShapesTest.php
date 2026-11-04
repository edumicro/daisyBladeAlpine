<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
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

it('table: a ResourceCollection without pagination is a table with an empty meta', function () {
    $read = componentReads('tableRows', laravelBodyOf(AnswerShapeResource::collection(someModels(2))));

    expect($read['result']['rows'])->toHaveCount(2)
        ->and($read['result']['meta'])->toBe([]);
});

it('table: a bare list is not a table answer', function () {
    expect(componentReads('tableRows', laravelBodyOf(someModels(2)))['error'])->toBe('shape');
});

it('lists (select options, calendar): a bare array, a ResourceCollection and a paginator are lists', function () {
    $options = collect([['value' => 1, 'label' => 'One'], ['value' => 2, 'label' => 'Two']]);
    $paginated = new LengthAwarePaginator($options, 2, 10, 1, ['path' => '/o']);

    expect(componentReads('selectOptions', laravelBodyOf($options))['result'])->toHaveCount(2)
        ->and(componentReads('selectOptions', laravelBodyOf($paginated))['result'])->toHaveCount(2)
        ->and(componentReads('selectOptions', json_encode(['data' => $options]))['result'])->toHaveCount(2);
});

it('detail: a JsonResource is unwrapped from its data', function () {
    $read = componentReads('resource', laravelBodyOf(new AnswerShapeResource(someModels(1)->first())));

    expect($read['result'])->toBe(['id' => 1, 'name' => 'Name 1']);
});

it('detail: a model with a `data` column returned as it is stays whole', function () {
    $read = componentReads('resource', laravelBodyOf(someModels(1)->first()));

    expect($read['result']['name'])->toBe('Name 1')
        ->and($read['result']['data'])->toBe(['color' => 'red']);
});

it('detail: a list of models is not a detail', function () {
    expect(componentReads('resource', laravelBodyOf(someModels(2)))['error'])->toBe('shape');
});
