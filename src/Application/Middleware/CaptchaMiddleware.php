<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Application\Middleware;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Utility\ClientApp;
use CodeLathe\Core\Utility\ContainerFacade;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\LoggerFacade;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Class CaptchaMiddleware
 *
 * Verifies Google ReCaptcha tokens
 *
 * @package CodeLathe\Application\Middleware
 */
class CaptchaMiddleware implements MiddlewareInterface
{

    protected const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * Minimal score required to trust on the requester
     *
     * @var float
     */
    protected $minScore;

    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    public function __construct(float $minScore = 0.5)
    {
        $this->minScore = $minScore;
        $this->httpClient = new Client();
        $this->config = ContainerFacade::get(ConfigRegistry::class);
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws InvalidFailedHttpCodeException
     * @throws GuzzleException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        // skip if the system is running in tests mode
        if ($this->config->get('/app/mode') === 'tests') {
            return $handler->handle($request);
        }

        // check if we should bypass captcha for mobile
        $mobileBypass = false;
        $whitelist = $this->config->get('/captcha/mobile_bypass');
        if (!empty($whitelist)) {

            $userAgent = $request->getHeader('User-Agent')[0] ?? null;
            $whitelist = explode(',', $whitelist);

            // bypass if the agent is an allowed mobile app
            $mobileBypass = in_array(ClientApp::identifyFromUserAgent($userAgent), $whitelist);
        }

        // only check captcha if it's enabled on config, and mobile bypass is false
        if ($this->config->get('/captcha/enabled') && !$mobileBypass) {

            // try to grab the recaptcha token from the headers
            $versions = ['V3', 'V2', 'Android']; // v3 for web, v2 for web failover and Android

            foreach ($versions as $version) {
                $reCaptchaToken = $request->getHeader("X-ReCaptcha$version-Token");
                if (!empty($reCaptchaToken)) {
                    break;
                }
            }

            // if no captcha token was provided, halt
            if (empty($reCaptchaToken)) {
                LoggerFacade::debug('No captcha token provided.');
                return JsonOutput::error('Captcha validation failed', 409)->write(new Response());
            }

            $reCaptchaToken = $reCaptchaToken[0];
            $version = strtolower($version);

            // request Google ReCaptcha API to check the validity/score of the token
            $validationResponse = $this->httpClient->request('POST', static::VERIFY_URL, [
                'form_params' => [
                    'secret' => $this->config->get("/captcha/{$version}/secret"),
                    'response' => $reCaptchaToken,
                    'remoteip' => $_SERVER['REMOTE_ADDR'],
                ]
            ]);

            // halt if the request failed, with a 503 (try again later). Should never happen.
            if ($validationResponse->getStatusCode() > 299) {
                LoggerFacade::debug('Error accessing recaptcha server: ' . print_r($validationResponse->getBody()->getContents(), true));
                return JsonOutput::error('Server error validating your request. Try again later.', 503)->write(new Response());
            }

            // parse the response
            $validationData = \GuzzleHttp\json_decode($validationResponse->getBody()->getContents(), true);

            // halt case the validation is not successful (the reason doesn't really matter)
            if (!$validationData['success']) {
                LoggerFacade::debug('Captcha validation failed: ' . print_r($validationData, true));
                return JsonOutput::error('Captcha validation failed', 409)->write(new Response());
            }

            // if there is a score field on the response, it's a v3 call, so check the min score
            if (!empty($validationData['score']) && $validationData['score'] < $this->minScore) {
                LoggerFacade::debug('Captcha validation failed (low score): ' . print_r($validationData, true));
                return JsonOutput::error('Captcha validation failed', 409)->write(new Response());
            }

            // if there is no score, it means a v2 check, so just the success response is enough

        }

        // handle...
        return $handler->handle($request);

    }

}