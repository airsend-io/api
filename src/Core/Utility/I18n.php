<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;


use Illuminate\Translation\Translator;
use PHPUnit\Exception;
use Psr\Container\ContainerInterface;

abstract class I18n
{

    /**
     * @var Translator
     */
    protected static $i18n;

    /**
     * @param ContainerInterface $container
     */
    public static function setUp(ContainerInterface $container)
    {
        static::$i18n = $container->get(Translator::class);
    }

    /**
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @param bool $fallback
     * @return mixed
     */
    public static function get(string $key, array $replace = [], ?string $locale = null, bool $fallback = true)
    {
        $locale = str_replace('-', '_', $locale);
        $translated = static::$i18n->get($key, $replace, $locale, $fallback);
        return !empty($translated) ? $translated : static::$i18n->get($key, $replace, 'en_US', $fallback);
    }

    /**
     * @param string $key
     * @param $number
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    public static function choice(string $key, $number, array $replace = [], ?string $locale = null)
    {
        $locale = str_replace('-', '_', $locale);
        $translated = static::$i18n->choice($key, $number, $replace, $locale);
        return !empty($translated) ? $translated : static::$i18n->choice($key, $number, $replace, 'en_US');
    }

    public static function setLocale(?string $locale): void
    {
        if ($locale !== null) {
            try {
                static::$i18n->setLocale(str_replace('-', '_', $locale));
            }
            catch (\Exception $e)
            {
                LoggerFacade::error("Exception [" . $e->getMessage() . "] setting locale - $locale");
            }
        }
    }

    public static function supportedLocaleList(): array
    {
        return [
            'en-US',
            'pt-BR',
            'es-ES',
            'fr-FR',
            'nl-NL',
            'de_DE',
        ];
    }
}