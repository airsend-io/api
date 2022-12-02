<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Objects;

/**
 * Class TranslatedPath
 *
 *
 * @package CodeLathe\Core\Objects
 */
class TranslatedPath
{

    /**
     * @var string
     */
    protected $physicalPath;

    /**
     * @var string|null
     */
    protected $relativePath;

    /**
     * @var Channel|null
     */
    protected $channel;

    /**
     * @var string
     */
    protected $displayPath;

    /**
     * @var Team
     */
    protected $team;

    /**
     * @var string|null
     */
    protected $type;

    /**
     * @var string|null
     */
    protected $channelDisplayPath;

    /**
     * TranslatedPath constructor.
     * @param string $physicalPath
     * @param string $displayPath
     * @param Team $team
     * @param string|null $relativePath
     * @param string|null $channelDisplayPath
     * @param Channel|null $channel
     * @param string|null $type Can be 'file' or 'wiki' (only applies to channel paths)
     */
    public function __construct(string $physicalPath,
                                string $displayPath,
                                Team $team,
                                ?string $relativePath = null,
                                ?string $channelDisplayPath = null,
                                ?Channel $channel = null,
                                ?string $type = null)
    {
        if (preg_match('/^\/f(\/.*)/', $physicalPath, $matches)) {
            $physicalPath = $matches[1];
        }
        $this->physicalPath = $physicalPath;
        $this->displayPath = $displayPath;
        $this->relativePath = $relativePath;
        $this->channelDisplayPath = preg_replace('/^\//', '', $channelDisplayPath);
        $this->channel = $channel;
        $this->team = $team;
        $this->type = $type;
    }

    public function getPhysicalPath(): string
    {
        return $this->physicalPath;
    }

    /**
     * Just a convenience method to return the Physical path prefixed with the /f
     *
     * @return string
     */
    public function getPrefixedPath(): string
    {
        return '/f' . $this->getPhysicalPath();
    }

    public function getDisplayPath(): string
    {
        return $this->displayPath;
    }

    /**
     * Returns the relative path, from the channel root
     * @return string|null
     */
    public function getChannelDisplayPath(): ?string
    {
        return $this->channelDisplayPath;
    }

    public function getRelativePath(): ?string
    {
        return $this->relativePath;
    }

    public function getBaseRelativePath(): ?string
    {
        if ($this->relativePath === null) {
            return null;
        }
        if (!preg_match('/^\/[wc]f\/[0-9]+/', $this->relativePath, $matches)) {
            return null;
        }
        return $matches[0];
    }

    /**
     * Convenience method that returns the relative path if it exists and the physical prefixed path if not
     * @return string
     */
    public function getPath(): string
    {
        return $this->relativePath ?? $this->getPrefixedPath();
    }

    public function getChannel(): ?Channel
    {
        return $this->channel;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getName(): string
    {
        if (preg_match('/[^\/]+$/', $this->physicalPath, $matches)) {
            return $matches[0];
        }
        return '';
    }
}
