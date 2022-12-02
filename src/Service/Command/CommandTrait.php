<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Service\Command;

use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\I18n;

/**
 * Describes a Service instance.
 */
trait CommandTrait
{
    private $arguments;

    private $options;

    /**
     * @var Channel
     */
    protected $channel;

    /**
     * @var User
     */
    protected $user;

    /**
     * @return string
     */
    abstract protected function signature(): string;

    /**
     * @param string $arg
     * @return string|null
     */
    protected function argument(string $arg): ?string
    {
        return $this->arguments[$arg] ?? null;
    }

    /**
     * @return string[]
     */
    protected function arguments(): array
    {
        return $this->arguments ?? [];
    }

    /**
     * @param string $option
     * @return string|null
     */
    protected function option(string $option)
    {
        return $this->options[$option] ?? null;
    }

    /**
     * @return string[]
     */
    protected function options(): array
    {
        return $this->options ?? [];
    }

    /**
     * Options always follow the patterns --option or --option=
     * On the first case, the option will always be a boolean value (false if not provided)
     * On the second case, it will be the provided value, or null if not provided (options are never required at this
     * point)
     * It's possible to define a default value, like: --option=default
     * It's possible to declare an option as an array, like this: --option=*. At this case, this option can be used
     * multiple times, and the values will be included on the same array.
     * It's also possible to use aliases to the same option, like: {--U|users=}. This way --U=jeff or --user=jeff will
     * have the same behaviour. It also works for boolean options
     *
     * @param array $params
     * @return bool
     */
    protected function parseOptions(string &$params): bool
    {
        preg_match_all('/\{([^}]+)\}/', $this->signature(), $matches);

        // split the options on signature
        $signatures = array_filter($matches[1], function($item) {
            return preg_match('/^--/', $item);
        });

        // extract options from the params string
        $regex = '/--([^\s=]+(?:=[^\s]+)?)+/';
        preg_match_all($regex, $params, $matches);
        $extractedOptions = $matches[1];
        $params = preg_replace($regex, '', $params);

        foreach ($signatures as $signature) {

            $signature = preg_replace('/^--/' , '', $signature);

            // boolean options
            if (preg_match('/^([a-zA-Z0-9](?:[a-zA-Z0-9|]*[a-zA-Z0-9])?)$/', $signature, $matches)) {
                $keys = explode('|', $matches[1]);
                $mainKey = $keys[count($keys) - 1];
                $this->options[$mainKey] = false;
                foreach ($extractedOptions as $param) {
                    if (in_array($param, $keys)) {
                        $this->options[$mainKey] = true;
                    }
                }
                continue;
            }

            // value
            if (preg_match('/^([a-zA-Z0-9](?:[a-zA-Z0-9|]*[a-zA-Z0-9])?)=([^*]+)?$/', $signature, $matches)) {
                $keys = explode('|', $matches[1]);
                $mainKey = $keys[count($keys) - 1];
                $defaultValue = $matches[2] ?? null;
                foreach ($extractedOptions as $param) {
                    if (!preg_match('/^[^=]+=[^=]+$/', $param)) {
                        continue; //ignore options without a value
                    }
                    [$key, $value] = explode('=', $param);
                    if (in_array($key, $keys)) {
                        $this->options[$mainKey] = $value;
                    }
                }

                // no definition found for the option
                if (empty($this->options[$mainKey])) {
                    $this->options[$mainKey] = $defaultValue ?? null;
                }
                continue;
            }

            // array
            if (preg_match('/^([a-zA-Z0-9](?:[a-zA-Z0-9|]*[a-zA-Z0-9])?)=\*$/', $signature, $matches)) {
                $keys = explode('|', $matches[1]);
                $mainKey = $keys[count($keys) - 1];
                foreach ($extractedOptions as $param) {
                    if (!preg_match('/^[^=]+=[^=]+$/', $param)) {
                        continue; //ignore options without a value
                    }
                    [$key, $value] = explode('=', $param);
                    if (in_array($key, $keys)) {
                        $this->options[$mainKey][] = $value;    
                    }
                }
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Arguments are defined by the order that they're provided on the command
     * They can be required {arg} or optional {arg?}, but a required argument can't come after an optional argument.
     * It's not recommended to mix options with arguments.
     * It's possible to define default values to arguments (which makes the argument automatically optional): {arg=default}
     * Optional arguments without a default value, will default to null
     * It's possible to include an specific regex constraint to the argument: {arg:[a-z]+}.
     * It's possible to mix optional arguments with regex: {arg?:[a-z]+} or {arg=default:[a-z]+}
     * The default regex used (when there's no regex defined) is: [^\s]+. The delimiter for the regex is always a /.
     *
     * @param string $params
     * @return bool
     */
    protected function parseArguments(string $params): bool
    {

        preg_match_all('/\{(.+?)\}(?:\s|$)/', $this->signature(), $matches);

        // split the arguments on signature
        $signatures = array_filter($matches[1], function($item) {
            return !preg_match('/^--/', $item);
        });

        // parse each argument signature
        $signatures = array_map(function ($item) {
            preg_match('/^([^:=?]+)(\?)?(?:=([^:]+))?(?:\:(.*))?$/', trim($item), $matches);
            return [
                'key' => $matches[1],
                'required' => empty($matches[2]) && empty($matches[3]),
                'default' => $matches[3] ?? null,
                'pattern' => $matches[4] ?? '[^\s]+'

            ];
        }, $signatures);

        foreach ($signatures as $signature) {
            $params = trim($params);
            $regex = "/^{$signature['pattern']}/";
            preg_match($regex, $params, $matches);
            $params = preg_replace($regex, '', $params);
            if (!empty($matches[0])) {
                $this->arguments[$signature['key']] = $matches[0];
            } else {
                if ($signature['required']) {
                    return false;
                }
                $this->arguments[$signature['key']] = !empty($signature['default']) ? $signature['default'] : null;
            }
        }

        // if there is still arguments on the args string, return false (invalid command)
        if (!empty(trim($params))) {
            return false;
        }

        return true;
    }

    protected function checkCommand(string $command): bool
    {
        [$expectedCommand] = explode(' ', trim($this->signature()));
        $expectedCommandTranslated = I18n::get("commands.$expectedCommand", [], $this->channel->getLocale());

        $command = trim(strtolower($command));

        return $command === $expectedCommand || $command === $expectedCommandTranslated;
    }

    public function setUp(Channel $channel, User $user)
    {
        $this->channel = $channel;
        $this->user = $user;
    }

    /**
     * @param string $command
     * @param string $params
     * @return bool
     */
    public function validateSignature(string $command, string $params): bool
    {

        if (!$this->checkCommand($command)) {
            return false;
        }

        if (!$this->parseOptions($params)) {
            return false;
        }

        if (!$this->parseArguments($params)) {
            return false;
        }

        return true;
    }

    public function commandUiSignature(string $pattern)
    {
        if (!preg_match('/\/([^\s]+)\s+(.*)$/', trim($pattern), $matches)) {
            return $pattern;
        }

        $originalCommand = $matches[1];
    }

    public function defaultUiSignature(string $command, bool $hasParams, int $level)
    {
        return [
            'command' => I18n::get("commands.{$command}_signature", []),
            'result' => I18n::get("commands.{$command}_fill", []),
            'hasParams' => $hasParams,
            'level' => $level,
            'description' => I18n::get("commands.{$command}_desc", []),
        ];
    }
}