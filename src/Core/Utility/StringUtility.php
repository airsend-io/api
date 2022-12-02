<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Utility;

use CodeLathe\Application\ConfigRegistry;
use Psr\Log\LoggerInterface;
use Snipe\BanBuilder\CensorWords;

class StringUtility
{
    public static function str_replace_first($search, $replace, $subject) {
        $pos = strpos($subject, $search);
        if ($pos !== false) {
            return substr_replace($subject, $replace, $pos, strlen($search));
        }
        return $subject;
    }


    /**
     * Helper function to generate random string
     *
     * @param int $length
     * @param string $keyspace
     * @return string
     * @throws \Exception
     */
    public static function generateRandomString(
        int $length = 6,
        string $keyspace = '0123456789abcdefghijklmnopqrstuvwxyz'
    ): string
    {
        $pieces = [];
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces []= $keyspace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @param bool $case verify case match
     * @return bool true if it haystack starts with supplied needle. false otherwise
     */
    public static function startsWith(string $haystack, string $needle, bool $case = false)
    {
        if ($case) {
            return (strcmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
        }
        return (strcasecmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @param bool $case verify case match
     * @return bool true if it haystack ends with supplied needle. false otherwise
     */
    public static function endsWith(string $haystack, string $needle, bool $case = false)
    {
        if ($case) {
            return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
        }
        return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
    }


    public static function replaceEndString($search, $replace, $subject)
    {
        $pos = strrpos($subject, $search);

        if($pos !== false)
        {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }


    public static function containsProfanity(string $haystack, &$violatingWords = [])
    {
        try {
            $censor = new CensorWords();
            $censor->setDictionary(Directories::resources('profanity/profanity_filter.php'));
            $upstring = $censor->censorString($haystack, true);
            if ($upstring['clean'] == $haystack)
            {
                return false;
            }
        }
        catch(\Exception $ex)
        {
            $violatingWords = [];
            return true; // something goes wrong with profanity filter, still continue
        }

        $violatingWords = $upstring['matched'];
        return true;
    }


    /**
     * @param string $text
     * @return mixed
     */
    public static function replaceWithEmojis(string $text)
    {
            $emojis = [
                'o/'         => '👋',
                '</3'        => '💔',
                '<3'         => '💗',
                '8-D'        => '😁',
                '8D'         => '😁',
                ':-D'        => '😁',
                '=-3'        => '😁',
                '=-D'        => '😁',
                '=3'         => '😁',
                '=D'         => '😁',
                'B^D'        => '😁',
                'X-D'        => '😁',
                'XD'         => '😁',
                'x-D'        => '😁',
                'xD'         => '😁',
                ':\')'       => '😂',
                ':\'-)'      => '😂',
                ':-))'       => '😃',
                '8)'         => '😄',
                ':)'         => '🙂',
                ':-)'        => '🙂',
                ':3'         => '😄',
                ':D'         => '😄',
                ':]'         => '😄',
                ':^)'        => '😄',
                ':c)'        => '😄',
                ':o)'        => '😄',
                ':}'         => '😄',
                ':っ)'        => '😄',
                '=)'         => '😄',
                '=]'         => '😄',
                '0:)'        => '😇',
                '0:-)'       => '😇',
                '0:-3'       => '😇',
                '0:3'        => '😇',
                '0;^)'       => '😇',
                'O:-)'       => '😇',
                '3:)'        => '😈',
                '3:-)'       => '😈',
                '}:)'        => '😈',
                '}:-)'       => '😈',
                '*)'         => '😉',
                '*-)'        => '😉',
                ':-,'        => '😉',
                ';)'         => '😉',
                ';-)'        => '😉',
                ';-]'        => '😉',
                ';D'         => '😉',
                ';]'         => '😉',
                ';^)'        => '😉',
                ':-|'        => '😐',
                ':|'         => '😐',
                ':('         => '😞',
                ':-('        => '😞',
                ':-<'        => '😒',
                ':-['        => '😒',
                ':-c'        => '😒',
                ':<'         => '😒',
                ':['         => '😒',
                ':c'         => '😒',
                ':{'         => '😒',
                ':っC'       => '😒',
                '%)'         => '😖',
                '%-)'        => '😖',
                ':-P'        => '😜',
                ':-b'        => '😜',
                ':-p'        => '😜',
                ':-Þ'        => '😜',
                ':-þ'        => '😜',
                ':P'         => '😜',
                ':b'         => '😜',
                ':p'         => '😜',
                ':Þ'         => '😜',
                ':þ'         => '😜',
                ';('         => '😜',
                '=p'         => '😜',
                'X-P'        => '😜',
                'XP'         => '😜',
                'd:'         => '😜',
                'x-p'        => '😜',
                'xp'         => '😜',
                ':-||'       => '😠',
                ':@'         => '😠',
                ':-.'        => '😡',
                ':-/'        => '😟',
                ':/'         => '😟',
                ':L'         => '🤔',
                ':S'         => '😕',
                ':-S'         => '😕',
                ':\\'        => '😡',
                '=/'         => '🤔',
                '=L'         => '🤔',
                '=\\'        => '😡',
                '^5'         => '😤',
                '^<_<'       => '😤',
                'o/\\o'      => '😤',
                '|-O'        => '😫',
                '|;-)'       => '😫',
                ':###..'     => '😷',
                ':-###..'    => '🤢',
                'D-\':'      => '😱',
                'D8'         => '😱',
                'D:'         => '😱',
                'D:<'        => '😱',
                'D;'         => '😱',
                'D='         => '😱',
                'DX'         => '😱',
                'v.v'        => '😱',
                '8-0'        => '😲',
                ':-O'        => '😲',
                ':-o'        => '😲',
                ':O'         => '😲',
                ':o'         => '😲',
                'O-O'        => '😲',
                'O_O'        => '😲',
                'O_o'        => '😲',
                'o-o'        => '😲',
                'o_O'        => '😲',
                'o_o'        => '😲',
                ':$'         => '😳',
                '#-)'        => '😵',
                ':#'         => '😶',
                ':&'         => '😶',
                ':-#'        => '😶',
                ':-&'        => '😶',
                ':-X'        => '😶',
                ':X'         => '🤐',
                ':-J'        => '😼',
                ':*'         => '😽',
                ':^*'        => '😽',
                'ಠ_ಠ'        => '🙅',
                '*\\0/*'     => '🙆',
                '\\o/'       => '🙆',
                ':>'         => '😄',
                '>.<'        => '😡',
                '>:('        => '😠',
                '>:)'        => '😈',
                '>:-)'       => '😈',
                '>:/'        => '😡',
                '>:O'        => '😲',
                '>:P'        => '😜',
                '>:['        => '😒',
                '>:\\'       => '😡',
                '>;)'        => '😈',
                '>_>^'       => '😤',
                '(y)'        => '👍',
                '(Y)'        => '👍',
                '(n)'        => '👎',
                '(N)'        => '👎',
                ':\'-('      => '😢',
                ':\'('       => '😭',
                '\',:-|'     => '🤨',
                '@};-'       => '🌹'
            ];


            $output = [];
            foreach (explode(' ',$text) as $word) {
                $output[] = $emojis[$word] ?? $word;
            }
            return implode(' ', $output);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public static function generateShortUrlHash(): string
    {
        return static::generateRandomString(6, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }

    public static function generateShortUrlFromHash(?string $hash): ?string
    {

        if (empty($hash)) {
            return null;
        }

        /** @var ConfigRegistry $config */
        $config = ContainerFacade::get(ConfigRegistry::class);
        return $config->get('/app/baseurl') . '/u/' . $hash;

    }

    public static function normalizeCacheKey(string $key): string
    {
        return str_replace(str_split('{}()/\\@:'), '_', $key);
    }

}