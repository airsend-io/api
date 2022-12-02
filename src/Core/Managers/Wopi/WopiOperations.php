<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\Wopi;


use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\BadResourceTranslationException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\WopiDiscoveryException;
use CodeLathe\Core\Exception\WopiOpsException;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Auth;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Service\Auth\JwtServiceInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;

class WopiOperations
{
    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FileOperations
     */
    protected $fOps;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    protected $jwt;


    CONST JWT_KEY = "fefe322-f66e-4b50-a990-6383b7c95908";


    /**
     * ChannelManager constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param FileOperations $fOps
     * @param EventManager $eventManager
     * @param ConfigRegistry $config
     * @param CacheItemPoolInterface $cache
     * @param JwtServiceInterface $jwt
     */
    public function __construct(DataController $dataController,
                                LoggerInterface $logger,
                                FileOperations $fOps,
                                EventManager $eventManager,
                                ConfigRegistry $config,
                                CacheItemPoolInterface $cache,
                                JwtServiceInterface $jwt
                                )
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->fOps = $fOps;
        $this->eventManager = $eventManager;
        $this->config = $config;
        $this->cache = $cache;
        $this->jwt = $jwt;
    }


    /**
     * @param string $path
     * @param User $user
     * @param bool $writeAccessReqd
     * @return bool
     * @throws BadResourceTranslationException
     * @throws UnknownResourceException
     */
    public function accessAllowed(string $path, User $user, bool $writeAccessReqd=false)
    {

        $translatedPath = $this->fOps->translatePath($path);

        if ($writeAccessReqd && $user->cannot('upload', $translatedPath)) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: No Permissions writing $fspath for ".$user->getDisplayName());
            return false;
        }

        if ($user->cannot('read', $translatedPath)) {
            $this->logger->error(__CLASS__.":".__FUNCTION__. " Error: No Permissions reading $fspath for ".$user->getDisplayName());
            return false;
        }

        return true;
    }

    /**
     * Discover specific end point from Microsoft address (https://onenote.officeapps.live.com) to use with
     * the editing process. This data will be cached.
     *
     * @return SimpleXMLElement
     * @throws WopiDiscoveryException
     * @throws InvalidArgumentException
     */
    private function getWopiEndpoints(): SimpleXMLElement
    {

        $wopiObj = $this->cache->getItem('airsend.wopi.discovery.ep');

        if (!$wopiObj->isHit()) {
            $wopiXML = "";
            if (!$this->discoverWopiEndpoints($wopiXML)) {
                //$this->logger->info("End point XML = " . $wopiXML);
                throw new WopiDiscoveryException("Failed discovering office end points");
            }
            $wopiObj->set($wopiXML);
            $wopiObj->expiresAfter(24*60*60); // one day expiration
            $this->cache->save($wopiObj);
        }
        else {
            $this->logger->debug(__FUNCTION__ . " : Got discovery end points from cache");
            $wopiXML = $wopiObj->get();
        }

        return new SimpleXMLElement($wopiXML);
    }

    /**
     * @param string $epXml
     * @return bool
     */
    private function discoverWopiEndpoints(string &$epXml): bool
    {
        $msDiscoveryEp = "https://onenote.officeapps.live.com/hosting/discovery";
        try {
            $httpClient = new Client();
            $res = $httpClient->get($msDiscoveryEp);

            if ($res->getStatusCode() == 200) {
                $epXml = $res->getBody()->getContents();
                return true;
            }

            $this->logger->error(__FUNCTION__ . " : $msDiscoveryEp returned code " . $res->getStatusCode());
        }
        catch (\Exception $e) {
            $this->logger->error(__FUNCTION__ . " : " . $e->getMessage());
        }
        return false;
    }

    /**
     *
     * Builds a edit endpoint for a file
     *
     * @param User $user
     * @param string $path File path to build a wopi link
     * @param $auth
     * @param string $action
     * @return string|null
     * @throws InvalidArgumentException
     * @throws WopiOpsException
     */
    public function getWopiEndpointForFile(User $user, string $path, Auth $auth, $action = "edit"): string
    {
        try {
            $file_type = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            $endPointsXMLObj = $this->getWopiEndpoints();
            if(empty($endPointsXMLObj)){
                $this->logger->error(__FUNCTION__ . ": WOPI discovery XML is empty or not well formed");
                throw new WopiDiscoveryException("Failed discovering office end points");
            }

            // Convert the legacy doc edits to convert action
            if($file_type == "doc" || $file_type == "ppt" || $file_type == "xls"){
                if($action == "edit") {
                    $action = "convert";
                }
            }

            $nodes = $endPointsXMLObj->xpath('//net-zone[@name="external-https"]//action[@ext="' . $file_type . '" and @name="'.$action.'"]');
            if (count($nodes) > 0) {
                $url = (string) $nodes[0]['urlsrc'];
                $url = preg_replace("/<.*>/", "", $url);
                $url = trim($url, "&");
                $url .= "&IsLicensedUser=1";
                if($action == "view"){
                    $url .= "&mode=view";
                }

                $sourceUrl = $this->getWOPISourceURL($user, $path, $auth);
                $url .= "&WOPISrc=$sourceUrl";

                $this->logger->info(__FUNCTION__ . " : [$url]");

                return $this->getHTMLTemplate($path, $url);
            }
        }
        catch (\Exception $e)
        {
            $this->logger->error(__FUNCTION__ . " : " . $e->getMessage());
            throw new WopiOpsException(__FUNCTION__ . ": Failed building file endpoint");
        }
        throw new WopiOpsException(__FUNCTION__ . " : Failed building file endpoint");
    }

    /**
     *
     * The URL for microsoft server to call back this server
     *
     * @param User $user
     * @param string $path
     * @param $auth
     * @return string
     */
    private function getWOPISourceURL(User $user, string $path, Auth $auth): string
    {
        //https://<uniqueid>.gw.getfilecloud.com/wopi.access/sadfasf243sa4
        // This is the Gateway ID
        $hosturl = rtrim($this->config->get('/app/wopi_relay_url'),'/').'/api/v1/';

        $scope = "/api/v1/wopi.edit";
        $scopedToken = $this->jwt->issueToken($auth->getUserId(), $auth->getClientIp(), $auth->getUserAgent(),
                $auth->getIsAdmin(), false, $scope);

        $keyClaims = ['path' => $path, 'user_id' => $user->getId(), 'user_name' => $user->getDisplayName(), 'token' => $scopedToken];

        $jwt = JWT::encode($keyClaims, self::JWT_KEY);
        return $hosturl. 'wopi.access/files/'.$jwt;
    }


    /**
     *
     * A prebuilt HTML template for UI to show
     *
     * @param $path
     * @param $url
     * @return string
     */
    private function getHTMLTemplate($path, $url)
    {

        $title = pathinfo($path, PATHINFO_BASENAME);


        $sandboxattr = "office_frame.setAttribute('sandbox', "
                    . "'allow-scripts allow-same-origin allow-forms "
                     . "allow-popups allow-top-navigation "
                     . "allow-popups-to-escape-sandbox');";


        $wopihostpage = "
<!doctype html>
<html>
<head>
    <meta charset=\"utf-8\">

    <!-- Enable IE Standards mode -->
    <meta http-equiv=\"x-ua-compatible\" content=\"ie=edge\">

    <title>$title</title>
    <meta name=\"description\" content=\"\">
    <meta name=\"viewport\"
          content=\"width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no\">


    <style type=\"text/css\">
        body {
            margin: 0;
            padding: 0;
            overflow:hidden;
            -ms-content-zooming: none;
        }
        #office_frame {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            margin: 0;
            border: none;
            display: block;
        }
    </style>
</head>
<body>

<form id=\"office_form\" name=\"office_form\" target=\"office_frame\"
      action=\"$url\" method=\"post\">
</form>

<span id=\"frameholder\"></span>
";

            $wopihostpage .= "
<script type=\"text/javascript\">
    var frameholder = document.getElementById('frameholder');
    var office_frame = document.createElement('iframe');
    office_frame.name = 'office_frame';
    office_frame.id ='office_frame';
    // The title should be set for accessibility
    office_frame.title = 'Office Online Frame';
    // This attribute allows true fullscreen mode in slideshow view
    // when using PowerPoint Online's 'view' action.
    office_frame.setAttribute('allowfullscreen', 'true');
    $sandboxattr
    frameholder.appendChild(office_frame);
    document.getElementById('office_form').submit();
</script>";

        $wopihostpage .= "</body></html> ";
        return $wopihostpage;
       }


}