<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Serializers;

use CodeLathe\Core\Utility\Json;
use Psr\Http\Message\ResponseInterface;

class JSONSerializer implements \JsonSerializable
{
    protected $ok;
    protected $error;
    protected $code;
    protected $name;
    protected $content = [];
    protected $httpCode;
    protected $metaArray = [];

    /**
     * JSONResponse constructor.
     * @param bool $ok whether the response is OK or not
     */
    public function __construct(bool $ok)
    {
        $this->ok = $ok;
        $this->httpCode = $ok ? 200 : 400;
    }

    /**
     * @param int $code
     * @return JSONSerializer
     */
    public function withCode(int $code) : JSONSerializer
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @param string $error
     * @return JSONSerializer
     */
    public function withError(string $error) : JSONSerializer
    {
        $this->error = $error;
        return $this;
    }

    /**
     * @param int $code
     * @return JSONSerializer
     */
    public function withhttpCode(int $code) : JSONSerializer
    {
        $this->httpCode = $code;
        return $this;
    }

    /**
     * @param string $name
     * @param $value
     * @return JSONSerializer
     */
    public function withContent(string $name, $value) : JSONSerializer
    {
        $this->content[$name] = $value;
        return $this;
    }

    public function addMeta(string $key, $value) : JSONSerializer
    {
        $this->metaArray[$key] = $value;
        return $this;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $this->metaArray['ok'] = $this->ok;
        if (isset($this->code)) {
            $this->metaArray['code'] = $this->code;
        }
        if (isset($this->error)) {
            $this->metaArray['error'] = $this->error;
        }

        $outArray = [];
        $outArray['meta'] = $this->metaArray;
        foreach ($this->content as $name => $value) {
            $outArray[$name] = $value;
        }
        return $outArray;
    }

    /**
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function write(ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(Json::encode($this, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($this->httpCode);
    }

}
