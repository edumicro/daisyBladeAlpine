<?php

namespace Edumicro\DaisyBlade\Tests\Catalog;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

/**
 * One way of using a component, with the props that it typically gets. The catalog (Catalog.php) is a list of these.
 *
 * Pattern "builder": `Example::of('form.input')->props([...])->texts('label')`, so that each line of the catalog says what
 * is special about that component and nothing else.
 *
 * What the tests do with an example:
 *   - paint it as it is, and painted again with a BAG OF ATTRIBUTES of a user (`withBag()`);
 *   - paint it with a hostile text in every prop of `texts` (`hostile()`);
 *   - open it in a browser with Alpine (the smoke test).
 */
final class Example
{
    /** What a user may write on any component: it must reach the element once, and the user's value must win. */
    public const BAG = [
        'class' => 'user-class',
        'id' => 'user-id',
        'x-on:click' => 'userClick()',
        '::disabled' => 'userDisabled',   // `::` so that Blade passes it as the text `:disabled`, not as PHP
        'placeholder' => 'user-placeholder',
        'rows' => '7',
        'type' => 'search',
        'data-user' => '1',
    ];

    /** A text that closes an attribute, opens a tag with a script that runs by itself, and breaks out of a JavaScript string. */
    public const HOSTILE = '"\'><img src=x onerror=alert(1)>\'+alert(1)+\'"+alert(1)+"';

    /** The mark that the texts of the CALLER carry in the catalog: they are not the component's to translate. */
    public const CALLER_MARK = '§';

    private array $props = [];

    private string $slot = '';

    /** @var list<string> paths (`label`, `items.*.title`) of the props that hold TEXT of the caller: the hostile one goes there. */
    private array $textPaths = [];

    /** @var list<string> modules that the browser must register (calendar, map, upload...). */
    private array $modules = [];

    private ?array $hostileProps = null;

    private ?string $scope = null;

    /** @var list<string> */
    private array $technicalTexts = [];

    private bool $forwardsAttributes = true;

    private ?string $notInBrowser = null;

    /** @var list<string> attributes of the bag that this component deliberately does not write on every element. */
    private array $bagExcept = [];

    private function __construct(public readonly string $component, public readonly string $variant)
    {
    }

    public static function of(string $component, string $variant = ''): self
    {
        return new self($component, $variant);
    }

    public function props(array $props): self
    {
        $this->props = $props;

        return $this;
    }

    /** The content between the tags (trusted HTML by definition: it is what the caller writes in their own view). */
    public function slot(string $slot): self
    {
        $this->slot = $slot;

        return $this;
    }

    /** The props (or paths inside them, with `*`) that hold text of the user, and so must be escaped. */
    public function texts(string ...$paths): self
    {
        $this->textPaths = $paths;

        return $this;
    }

    /** The props for the hostile paint, when the text is in a KEY of an array (a tree) and a path cannot reach it. */
    public function hostileProps(array $props): self
    {
        $this->hostileProps = $props;

        return $this;
    }

    /**
     * Texts that the component shows and that are not words of a language: a file extension, the JSON that a placeholder shows,
     * the codes `true` / `num` of a type selector. Listed one by one so that a real untranslated word cannot hide among them.
     */
    public function technicalTexts(string ...$texts): self
    {
        $this->technicalTexts = $texts;

        return $this;
    }

    public function isTechnical(string $text): bool
    {
        return in_array($text, $this->technicalTexts, true);
    }

    /** The component reads data that the page around it provides (`mode="alpine"` reads `data` and `errors`): the browser wraps it in this `x-data`. */
    public function inScope(string $xData): self
    {
        $this->scope = $xData;

        return $this;
    }

    /** The example as the browser opens it: painted, and inside the scope that the component expects, if any. */
    public function renderForBrowser(bool $hostile = false): string
    {
        $html = $this->render(hostile: $hostile);

        return $this->scope === null ? $html : '<div x-data="'.e($this->scope).'">'.$html.'</div>';
    }

    public function modules(string ...$modules): self
    {
        $this->modules = $modules;

        return $this;
    }

    /** This component does not hand the attributes of the user to any element (a wrapper that only lays out its slot). */
    public function withoutAttributeBag(): self
    {
        $this->forwardsAttributes = false;

        return $this;
    }

    /** The bag of the user leaves these attributes out for this component, on purpose (say why where you call it). */
    public function exceptInBag(string ...$names): self
    {
        $this->bagExcept = $names;

        return $this;
    }

    /** The smoke test in the browser skips it, and says why (it needs a service that a test does not have). */
    public function notInBrowser(string $reason): self
    {
        $this->notInBrowser = $reason;

        return $this;
    }

    /**
     * The bag of a user for THIS component: `Example::BAG` without the names that the view declares as props (`rows` is the data of
     * a table, `id` the prefix of an accordion). Written on the tag, those would be the props, not attributes of the element.
     *
     * @return array<string, string>
     */
    public function bag(): array
    {
        $source = file_get_contents(__DIR__.'/../../resources/views/daisyblade/'.str_replace('.', '/', $this->component).'.blade.php');
        preg_match('/@props\(\[(.*?)\]\)/s', $source, $block);
        preg_match_all("/'(\w+)'\s*(?:=>|,|\])/", ($block[1] ?? '').']', $names);
        $declared = array_map('strtolower', $names[1]);

        return array_filter(self::BAG, fn ($value, $name) => ! in_array(strtolower(ltrim($name, ':')), array_merge($declared, $this->bagExcept), true), ARRAY_FILTER_USE_BOTH);
    }

    public function name(): string
    {
        return $this->component.($this->variant === '' ? '' : " ({$this->variant})");
    }

    public function forwardsAttributes(): bool
    {
        return $this->forwardsAttributes;
    }

    public function modulesForBrowser(): array
    {
        return $this->modules;
    }

    public function whyNotInBrowser(): ?string
    {
        return $this->notInBrowser;
    }

    /** The example as the Blade of an application would write it. */
    public function template(array $bag = []): string
    {
        // A prop that the bag also writes would be the same attribute twice in the tag: the bag takes its place.
        $props = array_diff_key($this->props, $bag);
        $attributes = [];
        foreach ($props as $name => $value) {
            $attributes[] = ':'.Str::kebab($name).'="$props[\''.$name.'\']"';
        }
        foreach ($bag as $name => $value) {
            $attributes[] = $name.'="'.$value.'"';
        }

        return '<x-dbl::'.$this->component.' '.implode(' ', $attributes).'>'.$this->slot.'</x-dbl::'.$this->component.'>';
    }

    /** Paints it. With `$bag`, a user's attributes are on the tag; with `$hostile`, every text of the user is the hostile one. */
    public function render(array $bag = [], bool $hostile = false): string
    {
        $props = $hostile ? ($this->hostileProps ?? $this->withHostileTexts($this->props)) : $this->props;

        return Blade::render($this->template($bag), ['props' => $props]);
    }

    private function withHostileTexts(array $props): array
    {
        foreach ($this->textPaths as $path) {
            data_set($props, $path, self::HOSTILE);
        }

        return $props;
    }

    public function hasTexts(): bool
    {
        return $this->textPaths !== [] || $this->hostileProps !== null;
    }
}
