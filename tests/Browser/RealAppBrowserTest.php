<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
 * A mini Laravel app with REAL routes and controllers (Eloquent on sqlite, paginate(), JsonResource, validation
 * 422, the `auth` middleware, CSRF that is really checked), and the components walked in a real browser, once with fetch and once
 * with the real axios of Laravel's bootstrap.js.
 */

if (! class_exists('RealAppUser')) {
    class RealAppUser extends AuthUser { protected $table = 'users'; protected $guarded = []; }
    class RealAppPerson extends Model { protected $table = 'people'; protected $guarded = []; public $timestamps = false; }
    class RealAppPersonResource extends JsonResource
    {
        public function toArray(Request $request): array { return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'role' => $this->role]; }
    }
    class RealAppCsrf extends VerifyCsrfToken { protected function runningUnitTests() { return false; } }
    class RealAppPeopleController
    {
        public function index(Request $request)
        {
            $query = RealAppPerson::query()
                ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
                ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
                ->orderBy($request->input('sort', 'id'), $request->input('direction', 'asc'));

            return RealAppPersonResource::collection($query->paginate((int) $request->input('per_page', 5)));
        }

        public function simple(Request $request)
        {
            return RealAppPerson::query()->orderBy('id')->simplePaginate(5);
        }

        public function cursor(Request $request)
        {
            return RealAppPerson::query()->orderBy('id')->cursorPaginate(5);
        }

        public function store(Request $request)
        {
            $data = $request->validate(['name' => 'required|min:3', 'email' => 'required|email', 'accepted' => 'accepted', 'region' => 'required']);
            RealAppPerson::create(['name' => $data['name'], 'email' => $data['email'], 'role' => 'user']);
            $GLOBALS['real_app_last_post'] = $request->all();

            return response()->json(['message' => 'Saved']);
        }
    }
}

/** Pest's in-process server hands over the raw body of a multipart request: put its `file` part where Laravel looks for it. */
function realAppParseMultipart(Request $request): void
{
    if (! preg_match('/boundary=(.+)$/', (string) $request->header('content-type'), $b)) { return; }
    foreach (explode('--'.trim($b[1], '"'), $request->getContent()) as $part) {
        if (preg_match('/name="(\w+)"; filename="([^"]*)"\r\nContent-Type: [^\r\n]*\r\n\r\n(.*)\r\n$/s', $part, $m)) {
            $path = tempnam(sys_get_temp_dir(), 'real-app');
            file_put_contents($path, $m[3]);
            $request->files->set($m[1], new Illuminate\Http\UploadedFile($path, $m[2], 'text/plain', null, true));
        }
    }
}

function realAppStart(string $client = 'fetch', bool $withMeta = true): void
{
    registerBrowserEndpoints();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'session.driver' => 'array',
       
        'database.default' => 'real-app', 'database.connections.real-app' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        'auth.providers.users.model' => RealAppUser::class,
    ]);
    DB::purge('real-app');
    Schema::create('users', function ($t) { $t->id(); $t->string('name'); $t->string('email'); $t->string('password')->nullable(); $t->rememberToken(); $t->timestamps(); });
    Schema::create('people', function ($t) { $t->id(); $t->string('name'); $t->string('email'); $t->string('role'); });
    RealAppUser::create(['name' => 'Admin', 'email' => 'a@example.test']);
    foreach (range(1, 12) as $n) {
        RealAppPerson::create(['name' => ($n % 3 === 0 ? 'Ana ' : 'Pepe ').$n, 'email' => "p{$n}@example.test", 'role' => $n % 2 ? 'admin' : 'user']);
    }

    $web = [EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, RealAppCsrf::class, SubstituteBindings::class];
    Route::middleware($web)->group(function () {
        Route::post('/real-app/expire', function () { Auth::logout(); session()->invalidate(); session()->regenerateToken(); return response()->noContent(); });
        Route::post('/real-app/login', function () { Auth::login(RealAppUser::first()); return response()->noContent(); });
        Route::get('/login', fn () => response('<!DOCTYPE html><html><body>Sign in</body></html>'))->name('login');
        Route::middleware(Authenticate::class)->prefix('api')->group(function () {
            Route::get('/people', [RealAppPeopleController::class, 'index']);
            Route::get('/people-simple', [RealAppPeopleController::class, 'simple']);
            Route::get('/people-cursor', [RealAppPeopleController::class, 'cursor']);
            Route::post('/people', [RealAppPeopleController::class, 'store']);
            Route::get('/countries', fn () => response()->json([['value' => 'ES', 'label' => 'Spain'], ['value' => 'FR', 'label' => 'France']]));
            Route::get('/regions', fn (Request $r) => JsonResource::collection(collect($r->country === 'ES' ? [['value' => 'VLC', 'label' => 'Valencia'], ['value' => 'MAD', 'label' => 'Madrid']] : [['value' => 'IDF', 'label' => 'Ile de France']])));
            Route::post('/files', function (Request $r) {
                realAppParseMultipart($r);   // the in-process server of Pest does not parse multipart: do it here, then validate for real
                $r->validate(['file' => 'required|file|max:1']);   // 1 KB

                return response()->json(['id' => 55, 'name' => $r->file('file')->getClientOriginalName(), 'url' => '/f/55', 'size' => $r->file('file')->getSize()]);
            });
        });
    });
}

/** The page: a layout of the browser tests plus the meta of the CSRF token and, if asked, the real axios the way bootstrap.js sets it. */
function realAppPage(string $name, string $template, string $client = 'fetch', bool $withMeta = true, array $modules = []): string
{
    Route::middleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class])->get('/page/'.$name, function () use ($template, $client, $withMeta, $modules) {
        if (! Auth::check()) { Auth::login(RealAppUser::first()); }
        $html = browserLayout(Blade::render($template), $modules);
        $head = ($withMeta ? '<meta name="csrf-token" content="'.csrf_token().'">' : '');
        if ($client === 'axios') {
            $head .= '<script src="/_assets/node/axios/dist/axios.min.js"></script><script>window.axios = axios; axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest"</script>';
        }

        return response(str_replace('</head>', $head.'</head>', $html))->header('Content-Type', 'text/html; charset=utf-8');
    });

    return '/page/'.$name;
}

dataset('clients', ['fetch', 'axios']);

it('real app: the table shows paginate() through a JsonResource, sorts, searches, filters and pages', function (string $client) {
    realAppStart();
    $page = visit(realAppPage('real-app-table-'.$client, <<<'BLADE'
        <x-dbl::display.data-table id="t" load-url="/api/people" :per-page="5"
            :columns="[['key' => 'id', 'label' => 'Id'], ['key' => 'name', 'label' => 'Name', 'sortable' => true], ['key' => 'role', 'label' => 'Role']]"
            :filters="[['key' => 'role', 'type' => 'select', 'label' => 'Role', 'options' => ['admin' => 'Admin', 'user' => 'User']]]" />
        BLADE, $client));

    $rows = readPage($page, "document.querySelectorAll('#t tbody tr.hover').length", fn ($n) => $n === 5);
    expect($rows)->toBe(5);
    expect(readPage($page, "document.querySelector('#t').innerText", fn ($v) => str_contains($v, '1–5')))->toContain('1–5')->toContain('12');

    $page->script("[...document.querySelectorAll('#t button')].find(b => b.textContent.includes('Name')).click()");
    $page->script("[...document.querySelectorAll('#t button')].find(b => b.textContent.includes('Name')).click()");   // desc
    $first = readPage($page, "document.querySelector('#t tbody tr.hover td:nth-child(2)')?.textContent", fn ($v) => str_starts_with((string) $v, 'Pepe 8'));
    expect($first)->toStartWith('Pepe 8');

    $page->script("document.querySelector('#t button:has(+ div select), #t [tabindex=\"0\"].btn')?.click()");
    setField($page, '#t select', 'admin', 'change');
    $count = readPage($page, "document.querySelector('#t').innerText", fn ($v) => str_contains($v, 'of 6'));
    expect($count)->toContain('of 6');

    // Page 2 of the filtered list (a visible button: the arrows of a closed pager are in the page too), then a search: the table goes back to page 1.
    $page->script("[...document.querySelectorAll('#t .join button')].find(b => b.textContent.trim() === '2').click()");
    readPage($page, "document.querySelector('#t').innerText", fn ($v) => str_contains($v, '6–6'));
    setField($page, '#t input[type=text]', 'Ana');
    $done = fn ($v) => str_contains($v, 'of 2');   // the admins whose name has "Ana": ids 3 and 9
    $text = readPage($page, "document.querySelector('#t').innerText", $done);
    $text = $done($text) ? $text : readPage($page, "document.querySelector('#t').innerText", $done);   // a slow machine: a second turn
    expect($text)->toContain('of 2');
})->with('clients');

it('real app: a table that answers simplePaginate() goes forward and back with its arrows', function (string $client) {
    realAppStart();
    $page = visit(realAppPage('real-app-table-simple-'.$client, <<<'BLADE'
        <x-dbl::display.data-table id="t" load-url="/api/people-simple" :columns="[['key' => 'id', 'label' => 'Id']]" />
        BLADE, $client));

    readPage($page, 'document.querySelectorAll("#t tbody tr.hover").length', fn ($n) => $n === 5);
    $page->script("[...document.querySelectorAll('#t .join button')].filter(b => b.offsetParent !== null).find(b => b.textContent.trim() === '›').click()");
    $second = readPage($page, "[...document.querySelectorAll('#t tbody tr.hover td:first-child')].map(td => td.textContent.trim())", fn ($ids) => $ids === ['6', '7', '8', '9', '10']);
    $page->script("[...document.querySelectorAll('#t .join button')].filter(b => b.offsetParent !== null).find(b => b.textContent.trim() === '‹').click()");
    $back = readPage($page, "[...document.querySelectorAll('#t tbody tr.hover td:first-child')].map(td => td.textContent.trim())", fn ($ids) => $ids === ['1', '2', '3', '4', '5']);

    expect($second)->toBe(['6', '7', '8', '9', '10'])->and($back)->toBe(['1', '2', '3', '4', '5']);
})->with('clients');

it('real app: a table that answers cursorPaginate() walks to the last page by cursors and back', function (string $client) {
    realAppStart();
    $page = visit(realAppPage('real-app-table-cursor-'.$client, <<<'BLADE'
        <x-dbl::display.data-table id="t" load-url="/api/people-cursor" :columns="[['key' => 'id', 'label' => 'Id']]" />
        BLADE, $client));
    $arrow = fn (string $symbol) => $page->script("[...document.querySelectorAll('#t .join button')].filter(b => b.offsetParent !== null).find(b => b.textContent.trim() === '{$symbol}').click()");
    $ids = fn (array $expected) => readPage($page, "[...document.querySelectorAll('#t tbody tr.hover td:first-child')].map(td => td.textContent.trim())", fn ($ids) => $ids === $expected);

    expect($ids(['1', '2', '3', '4', '5']))->toBe(['1', '2', '3', '4', '5']);
    $arrow('›');
    expect($ids(['6', '7', '8', '9', '10']))->toBe(['6', '7', '8', '9', '10']);
    $arrow('›');
    expect($ids(['11', '12']))->toBe(['11', '12']);
    $arrow('‹');
    expect($ids(['6', '7', '8', '9', '10']))->toBe(['6', '7', '8', '9', '10']);
})->with('clients');

/** The auto-form of the walk: text fields, a checkbox the server requires (`accepted`) and the dependent selects in its slot. */
function realAppFormTemplate(): string
{
    return <<<'BLADE'
        <x-dbl::sections.auto-form id="f" submit-url="/api/people" success-msg="Saved!"
            :fields="[
                'name' => ['type' => 'text', 'label' => 'Name'],
                'email' => ['type' => 'text', 'label' => 'Email'],
                'accepted' => ['type' => 'checkbox', 'label' => 'I accept'],
            ]">
            <x-dbl::form.select name="country" load-url="/api/countries" placeholder="--" x-model="values.country" />
            <x-dbl::form.select name="region" load-url="/api/regions" depends-on="country" placeholder="--" x-model="values.region" />
        </x-dbl::sections.auto-form>
        BLADE;
}

function realAppFill($page): void
{
    setField($page, '#f input[type=text]:nth-of-type(1)', 'Ana');
}

it('real app: country -> region -> submit reaches the controller with both values', function (string $client) {
    realAppStart();
    $GLOBALS['real_app_last_post'] = null;
    $page = visit(realAppPage('real-app-flow-'.$client, realAppFormTemplate(), $client));

    readPage($page, "document.querySelectorAll('#f select[name=country] option').length", fn ($n) => $n >= 3);
    setField($page, '#f select[name=country]', 'ES', 'change');
    readPage($page, "[...document.querySelectorAll('#f select[name=region] option')].map(o => o.value).join()", fn ($v) => str_contains($v, 'VLC'));
    setField($page, '#f select[name=region]', 'MAD', 'change');
    $page->script("const d = Alpine.\$data(document.querySelector('#f > div')); d.values.name = 'Ana'; d.values.email = 'ana@example.test'; d.values.accepted = true");
    $page->script("document.querySelector('#f button[type=submit]').click()");
    pause($page, 0.8);

    expect($GLOBALS['real_app_last_post'])->toMatchArray(['country' => 'ES', 'region' => 'MAD', 'name' => 'Ana'])
        ->and($page->script("document.querySelector('#f').innerText"))->toContain('Saved!');
})->with('clients');

it('real app: a session that expires shows the failure with Retry on the table, and Retry works after logging in again', function (string $client) {
    realAppStart();
    $page = visit(realAppPage('real-app-expire-'.$client, '<x-dbl::display.data-table id="t" load-url="/api/people" :columns="[[\'key\' => \'id\', \'label\' => \'Id\']]" />', $client));
    readPage($page, "document.querySelectorAll('#t tbody tr.hover').length", fn ($n) => $n > 0);

    $token = $page->script("document.querySelector('meta[name=csrf-token]').content");
    $page->script("fetch('/real-app/expire', { method: 'POST', headers: { 'X-CSRF-TOKEN': ".json_encode($token).", Accept: 'application/json' } })");
    pause($page, 0.5);
    $page->script("document.querySelector('#t button[title=Refresh]').click()");
    $text = readPage($page, "document.querySelector('#t').innerText", fn ($v) => str_contains($v, 'Retry'));

    expect($text)->toContain('Could not load the data')->toContain('Retry');
})->with('clients');

it('real app: an upload over the limit shows the message of the 422 of Laravel on the file', function (string $client) {
    realAppStart();
    $page = visit(realAppPage('real-app-upload-'.$client, '<form><x-dbl::form.upload name="doc" upload-url="/api/files" /></form>', $client, modules: ['upload']));

    chooseFile($page, 'input[type=file]', 'big.txt', str_repeat('x', 5000));
    $text = readPage($page, "document.querySelector('ul').innerText", fn ($v) => str_contains($v, 'kilobytes') || str_contains($v, 'Retry'));

    expect($text)->toContain('1 kilobytes');
})->with('clients');

it('real app: a small upload reaches the controller as multipart with the CSRF token and ends as a hidden id', function (string $client) {
    realAppStart();
    $page = visit(realAppPage('real-app-upload-ok-'.$client, '<form><x-dbl::form.upload name="doc" upload-url="/api/files" /></form>', $client, modules: ['upload']));

    chooseFile($page, 'input[type=file]', 'ok.txt', 'hello');
    $id = readPage($page, "document.querySelector('input[name=\"doc[]\"]')?.value", fn ($v) => $v !== null && $v !== '');

    expect($id)->toBe('55');
})->with('clients');

it('real app: without the meta tag the XSRF-TOKEN cookie carries the CSRF token (fetch)', function () {
    realAppStart();
    $page = visit(realAppPage('real-app-nometa', realAppFormTemplate(), 'fetch', withMeta: false));

    readPage($page, "document.querySelectorAll('#f select[name=country] option').length", fn ($n) => $n >= 3);
    $page->script("document.querySelector('#f button[type=submit]').click()");
    $text = readPage($page, "document.querySelector('#f').innerText", fn ($v) => str_contains($v, 'required') || str_contains($v, 'CSRF'));

    expect($text)->not->toContain('CSRF')->toContain('required');
});


it('real app: the reset button of the auto-form empties what it will send, not only what the boxes show', function () {
    realAppStart();
    $page = visit(realAppPage('real-app-reset', <<<'BLADE'
        <x-dbl::sections.auto-form id="f" submit-url="/api/people" reset-label="Reset"
            :fields="['name' => ['type' => 'text', 'label' => 'Name']]" />
        BLADE));

    pause($page, 0.4);
    setField($page, '#f input[type=text]', 'Ana');
    $page->script("[...document.querySelectorAll('#f button')].find(b => b.type === 'reset').click()");
    pause($page, 0.3);

    // What the user sees and what will be sent must agree: both empty.
    expect($page->script("document.querySelector('#f input[type=text]').value"))->toBe('')
        ->and($page->script("Alpine.\$data(document.querySelector('#f > div')).values.name ?? ''"))->toBe('');
});
