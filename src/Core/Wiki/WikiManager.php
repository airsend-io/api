<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Wiki;

use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\BadResourceTranslationException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSAuthorizationException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\SecurityException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\UnknownUserException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Serializers\HTMLSerializer;
use CodeLathe\Core\Serializers\JSONSerializer;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\SafeFile;
use CodeLathe\Service\Auth\JwtServiceInterface;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;

class WikiManager extends ManagerBase
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FileOperations
     */
    protected $fops;

    /**
     * @var DataController
     */
    protected $dc;

    /**
     * @var JwtServiceInterface
     */
    protected $jwt;

    public function __construct(LoggerInterface $logger, DataController $dataController, FileOperations $fops, JwtServiceInterface $jwt)
    {
        $this->logger = $logger;
        $this->fops = $fops;
        $this->dc = $dataController;
        $this->jwt = $jwt;
    }

    private function tryTokenFromAuthorizationHeader(Request $request): ?string
    {
        if (
            !empty($authHeader = $request->getHeader('Authorization')) &&
            preg_match('/^Bearer\s+(.+)$/', trim($authHeader[0]), $matches)
        ) {
            return $matches[1];
        }
        return null;
    }

    protected function tryTokenFromQueryString(Request $request, string $varName = 'token'): ?string
    {
        return $request->getQueryParams()[$varName] ?? null;
    }

    /**
     * @param string $data
     * @param string $authHeader
     * @return string
     * @throws SecurityException
     */
    private function renderHTML(string $data, string $authHeader)
    {
        // ... Include MATH WIKI Headers?
        $includeMath = false;
        if (strpos($data, '[comment]: # (INCLUDE MATH)') != FALSE)
        {
            $includeMath = true;
        }

        $mathHeaders = '';
        if ($includeMath)
        {
            $mathHeaders ="
                         <link rel=\"stylesheet\" href=\"/api/v1/static.get/wiki/math/katex.min.css\" integrity=\"sha384-zB1R0rpPzHqg7Kpt0Aljp8JPLqbXI3bhnPWROx27a9N0Ll6ZP/+DiW/UqRcLbRjq\" crossorigin=\"anonymous\">
                             <script defer src=\"/api/v1/static.get/wiki/math/katex.min.js\" integrity=\"sha384-y23I5Q6l+B6vatafAwxRu/0oK/79VlbSz7Q9aiSZUvyWYIYsd+qj+o24G5ZU2zJz\" crossorigin=\"anonymous\"></script>
                             <script defer src=\"/api/v1/static.get/wiki/math/auto-render.min.js\" integrity=\"sha384-kWPLUVMOks5AQFrykwIup5lo0m3iMkkHrD0uJ4H5cjeGihAutqP0yW0J6dpFiVkI\" crossorigin=\"anonymous\"
    onload=\"renderMathInElement(document.body);\"></script>";
        }

        //$this->logger->debug(__CLASS__.":".__FUNCTION__. " Building Wiki Path Scope   ". $scope);

        $parsedown = new ASParsedown([
                                         'math' => [
                                             'enabled' => $includeMath // Write true to enable the module
                                         ]
                                     ]);
        $parsedown->setAuthHeader($authHeader);
        $parsedown->setSafeMode(true);
        $out = $parsedown->text($data);
        $css = SafeFile::file_get_contents(CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . FileOperations::getChannelWikiName() . DIRECTORY_SEPARATOR . 'css'.DIRECTORY_SEPARATOR.'default.css');
        $html = "<!DOCTYPE html>
                        <html>
                            <head>
                            <meta name=\"viewport\" content=\"width=device-width,initial-scale=1.0,maximum-scale=1, user-scalable=no\">
                            <style>" . $css . "</style>"
            .$mathHeaders.
            "</head>
                        <body>" . $out . "</body></html>";

        return $html;
    }


    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws SecurityException
     * @throws UnknownResourceException
     * @throws UnknownUserException
     * @throws BadResourceTranslationException
     */
    public function get(Request $request, Response $response, $args)
    {
        if (!isset($args['path'])) {
            return (new JSONSerializer(false))->write($response);
        }

        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $path = '/'.$args['path']; // Path ends up being wf/FOMAPPING/filename

        // check if the path is a .md file
        $downloadType = preg_match('/\.md$/', $path) ? 'local' : 'redirect';

        try {
            $downloadResource = $this->fops->download($path, null, $user, $downloadType);
        } catch (FSAuthorizationException $e) {
            $html = "<!DOCTYPE html><html><body>You're not allowed to access this path</body></html>";
            return (new HTMLSerializer(true))->withHTML($html)->write($response);
        } catch (FSOpException $e) {

            if (preg_match('/^\/wf\/[0-9]+\/index\.md$/', $path)) {
                $html = "<!DOCTYPE html><html><body>No content in wiki</body></html>";
            } else {
                $html = "<!DOCTYPE html><html><body>Storage Error: {$e->getMessage()}</body></html>";
            }
            return (new HTMLSerializer(true))->withHTML($html)->write($response);
        }

        // MD files need to be converted to HTML before being sent to UI
        // MD files are always returned as local download resources
        if ($downloadResource->getType() === 'local') {

            // get downloaded file data
            $localFile = $downloadResource->getPayload()['tmpfile'] ?? '';
            $data = file_get_contents($localFile);
            if ($data === false) {
                return JsonOutput::error("Storage Error")->write($response);
            }

            // html encode the file, so no html tags defined by the user is returned
            $data = htmlentities($data);

            // delete the local file
            unlink($localFile); // ensure that the file is deleted


            if ($user->getUserRole() === User::USER_ROLE_PUBLIC) {

                // if the access is made through a public hash, just pass it
                $authHeader = 'pub-' . $user->getPublicHash();

            } else {

                // if the access is made through a token, generate a new one, scoped for the wiki
                // get auth object
                /** @var Auth $auth */
                $auth = $request->getAttribute('auth');

                // set the scope to the wiki root of the path
                if (!preg_match('/^\/wf\/[0-9]+/', $path, $matches)) {
                    $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Wiki Path  ". $path);
                    $html = "<!DOCTYPE html><html><body>Bad Path</body></html>";
                    return (new HTMLSerializer(true))->withHTML($html)->write($response);
                }
                $scope = "/api/v1/wiki.get{$matches[0]}/*";

                // generate the token
                $authHeader = $this->jwt->issueToken(
                    $auth->getUserId(),
                    $auth->getClientIp(),
                    $auth->getUserAgent(),
                    $auth->getIsAdmin(),
                    false,
                    $scope
                );
            }

            // render the html and return
            $html = $this->renderHTML($data, $authHeader);
            return (new HTMLSerializer(true))->withHTML($html)->write($response);

        }

        // if the file is not a markdown file, just return it as regular redirect download
        try {
            return $downloadResource->handle($response);
        } catch (Exception $e) {
            return JsonOutput::error("Internal Storage Error", 500)->write($response);
        }

    }

    public function preview(Request $request, Response $response, $args)
    {
        if (!isset($args['path'])) {
            return (new JSONSerializer(false))->write($response);
        }

        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $path = '/'.$args['path']; // Path ends up being wf/FOMAPPING/filename

        try {
            $translatedPath = $this->fops->translatePath($path);
        } catch (FSOpException $e) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: Bad Wiki Path  ". $path);
            $html = "<!DOCTYPE html><html><body>Bad Path</body></html>";
            return (new HTMLSerializer(true))->withHTML($html)->write($response);
        }

        if ($user->cannot('read', $translatedPath)) {
            $html = "<!DOCTYPE html><html><body>Bad Permissions</body></html>";
            return (new HTMLSerializer(true))->withHTML($html)->write($response);
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (strtolower($ext) == "md") {

            // ... MD files need to be converted to HTML before being sent to UI

            $data = file_get_contents('php://input');
            if ($data === false) {
                $html = "<!DOCTYPE html><html><body>Bad Input Data for Preview</body></html>";
                return (new HTMLSerializer(true))->withHTML($html)->write($response);
            }

            // encode any htm tag send by the user
            $data = htmlentities($data);

            /** @var Auth $auth */
            $auth = $request->getAttribute('auth');
            $wikiBasePath = $translatedPath->getBaseRelativePath();

            $scope = "/api/v1/wiki.get" . $wikiBasePath;
            if ($user->getUserRole() === User::USER_ROLE_PUBLIC) {
                $authHeader = 'pub-' . $user->getPublicHash();
            } else {
                $authHeader = $this->jwt->issueToken(
                    $auth->getUserId(),
                    $auth->getClientIp(),
                    $auth->getUserAgent(),
                    $auth->getIsAdmin(),
                    false,
                    $scope
                );
            }

            //$this->logger->debug(__CLASS__.":".__FUNCTION__. " Building Wiki Path Scope   ". $scope);

            $html = $this->renderHTML($data, $authHeader);
            return (new HTMLSerializer(true))->withHTML($html)->write($response);
        }

        $html = "<!DOCTYPE html><html><body>Invalid FileName for Preview, must be md files</body></html>";
        return (new HTMLSerializer(true))->withHTML($html)->write($response);
     }

    /**
     * Derived class must give us the datacontroller
     * @return DataController
     */
    protected function dataController(): DataController
    {
        return $this->dc;
    }


}