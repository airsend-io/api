<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/
namespace CodeLathe\Core\Serializers;

class HTMLSerializer
{
    protected $ok;
    protected $error;
    protected $html;
    protected $httpcode;

    /**
     * HTMLSerializer constructor.
     * @param bool $ok whether the response is OK or not
     * @param int $code error code if available, null otherwise
     * @param string $error error message is available, blank otherwise
     * @param string $objname name of the serialized object
     * @param $obj a single object or an array of items
     */
    public function __construct(bool $ok)
    {
        $this->ok = $ok;
        $this->error = null;
        $this->objs = null;
        $this->httpcode = $ok ? 200 : 400;
    }

    public function withError(string $error) : HTMLSerializer
    {
        $this->error = $error;
        return $this;
    }

    public function withHTTPCode(int $code) : HTMLSerializer
    {
        $this->httpcode = $code;
        return $this;
    }

    public function withHTMLFile(string $name) : HTMLSerializer
    {
        $this->html = file_get_contents(CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'dev'.DIRECTORY_SEPARATOR.$name);
        return $this;
    }

    public function withHTML(string $html) : HTMLSerializer
    {
        $this->html = $html;
        return $this;
    }

    public function write(\Psr\Http\Message\ResponseInterface &$response)
    {
        $response->getBody()->write($this->html);
        return $response->withHeader('Content-Type', 'text/html')->withStatus($this->httpcode);
    }
};