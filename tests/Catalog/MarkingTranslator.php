<?php

namespace Edumicro\DaisyBlade\Tests\Catalog;

use Illuminate\Translation\Translator;

/**
 * A translator for tests that wraps EVERY text it translates in ⟦…⟧ (the marker language).
 * A page painted with it shows which texts went through `__()`: the one without the marker was written by hand in the view,
 * or came from a default of a prop that nobody translated. Missing keys are marked too: this is not the test of the files of lang.
 */
final class MarkingTranslator extends Translator
{
    public const OPENING = '⟦';

    public const CLOSING = '⟧';

    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $translation = parent::get($key, $replace, $locale, $fallback);

        return is_string($translation) ? self::OPENING.$translation.self::CLOSING : $translation;
    }

    public function choice($key, $number, array $replace = [], $locale = null)
    {
        return self::OPENING.parent::choice($key, $number, $replace, $locale).self::CLOSING;
    }

    /** Takes the place of the translator of the application, keeping its loader (and so its paths, namespaces and fallback). */
    public static function install($app): void
    {
        $app->extend('translator', function (Translator $translator) {
            $marking = new self($translator->getLoader(), $translator->getLocale());
            $marking->setFallback($translator->getFallback());

            return $marking;
        });
    }
}
