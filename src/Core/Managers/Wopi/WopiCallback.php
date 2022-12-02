<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/


namespace CodeLathe\Core\Managers\Wopi;


use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ASException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSAuthorizationException;
use CodeLathe\Core\Exception\FSNotFoundException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\FSOpServerException;
use CodeLathe\Core\Exception\SecurityException;
use CodeLathe\Core\Exception\UnknownPolicyEntityException;
use CodeLathe\Core\Exception\UnknownResourceException;
use CodeLathe\Core\Exception\UnknownUserException;
use CodeLathe\Core\Managers\Files\FileController;
use CodeLathe\Core\Managers\Files\FileOperations;
use CodeLathe\Core\Managers\Lock\LockOperations;
use CodeLathe\Core\Managers\ResourceManager;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Objects\Path;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\Directories;
use CodeLathe\Core\Utility\Json;
use CodeLathe\Core\Utility\JsonOutput;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request;
use Psr\Http\Message\ResponseInterface as Response;

class WopiCallback
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
     * @var FileController
     */
    protected $fc;

    /**
     * @var LockOperations
     */
    protected $lockOps;

    protected $wopiOps;

    CONST JWT_KEY = "fefe322-f66e-4b50-a990-6383b7c95908";


    /**
     * ChannelManager constructor.
     * @param DataController $dataController
     * @param LoggerInterface $logger
     * @param FileOperations $fOps
     * @param EventManager $eventManager
     * @param ConfigRegistry $config
     * @param FileController $fc
     * @param LockOperations $lockOps
     */
    public function __construct(DataController $dataController,
                                LoggerInterface $logger,
                                FileOperations $fOps,
                                EventManager $eventManager,
                                ConfigRegistry $config,
                                FileController  $fc,
                                LockOperations $lockOps,
                                WopiOperations $wopiOps
                                )
    {
        $this->dataController = $dataController;
        $this->logger = $logger;
        $this->fOps = $fOps;
        $this->eventManager = $eventManager;
        $this->config = $config;
        $this->fc = $fc;
        $this->lockOps = $lockOps;
        $this->wopiOps = $wopiOps;
    }


    /**
     *
     * Entry point to the request processing code from Office 365 service
     *
     * @param Request $request
     * @param Response $response
     * @param array $claims
     * @param array $args
     * @return void
     * @throws DatabaseException
     * @throws SecurityException
     * @throws UnknownPolicyEntityException
     * @throws UnknownResourceException
     * @throws UnknownUserException
     */
    public function route(Request $request, Response $response, array $claims, array $args): void
    {
        // The request microsoft has to be handled properly

        if (array_key_exists('contents', $args)) {
            $this->fileOperations($request, $response, $claims);
        } else {
            $this->controlOps($request, $response, $claims);
        }
    }

    /**
     *
     * Perform file get or upload operation
     *
     * @param Request $request
     * @param Response $response
     * @param array $claims
     * @return void
     * @throws SecurityException
     * @throws UnknownPolicyEntityException
     * @throws UnknownResourceException
     * @throws UnknownUserException
     */
    private function fileOperations(Request $request, Response $response, array $claims): void
    {
        if ($request->getMethod() === "GET") {
            $this->downloadFile($claims, $response);
        }
        else {
            $this->uploadFile($request, $claims);
        }
    }

    /**
     *
     * Respond to commands from O365
     *
     * @param Request $request
     * @param Response $response
     * @param array $claims
     * @return void
     * @throws DatabaseException
     * @throws UnknownResourceException
     * @throws UnknownUserException
     */
    private function controlOps(Request $request, Response $response, array $claims): void
    {
        $this->logger->info(__FUNCTION__  . print_r($request->getHeaders(),true));

        if ($request->getMethod() === "GET") {
            $this->getFileInfo($request, $response, $claims);
        } else {
            $wopiCommandHdr = $request->getHeader('HTTP_X_WOPI_OVERRIDE');
            if (!empty($wopiCommandHdr)) {
                $wopiCommand = $wopiCommandHdr[0];
                $this->logger->info(__FUNCTION__ . " : Command $wopiCommand from Office 365!");

                switch ($wopiCommand) {
                    case 'LOCK':
                        $this->lockFile($request, $response,$claims);
                        break;
                    case 'GET_LOCK':
                        $this->getLock($request, $response,$claims);
                        break;
                    case 'UNLOCK':
                        $this->unLockFile($request, $response,$claims);
                        break;
                    case 'REFRESH_LOCK':
                        $this->refreshLock($request, $response,$claims);
                        break;
                    case 'PUT_RELATIVE':
                    case 'DELETE':
                        $this->logger->info(__FUNCTION__ . " : Ignored command $wopiCommand");
                        break;
                    default:
                        $this->logger->info(__FUNCTION__ . " : Unknown command $wopiCommand from Office 365!");
                }
            }
        }
    }

    /**
     *
     * Return file information
     *
     * @param Request $request
     * @param Response $response
     * @param array $claims
     * @return void
     * @throws DatabaseException
     * @throws UnknownResourceException
     * @throws UnknownUserException
     * @throws \CodeLathe\Core\Exception\BadResourceTranslationException
     */
    private function getFileInfo(Request $request, Response $response, array $claims): void
    {

        $path = $claims['path'];
        $user_name = $claims['user_name'];
        $userId = (int)$claims['user_id'];
        $user = $this->dataController->getUserById($userId);
        if (empty($user)) {
            $this->logger->error(__FUNCTION__ . " : User $userId $user_name does not exist. Rejecting");
            return;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $base_file_name = pathinfo($path, PATHINFO_BASENAME);

        try {
            $file = $this->fOps->info($path, $user);
        } catch (FSAuthorizationException | FSNotFoundException | FSOpException $e) {
            // just return (keeping what we have before, but we could at least log something here)
            return;
        }

        $modIso = date_create($file->getModificationDate());
        
        $infoJson = array(
            "BaseFileName" => $base_file_name,
            "OwnerId" => $file->getBy(),
            "UserId" => $userId,
            "UserFriendlyName" => $user_name,
            "Size" => $file->getSize(),
            "Version" => $file->getModificationDate(),
            "LastModifiedTime" => $modIso->format("c"),
            "UserCanWrite" => true,
            "RestrictedWebViewOnly" => false,
            "ReadOnly" => false,
            "SupportsUpdate" => true,
            "SupportsLocks" => true,
            "SupportsGetLock" => true,
            "SupportsExtendedLockLength" => true,
            "SupportsCobalt" => false,
            "SupportsFolders" => false,
            "SupportsDeleteFile" => true,
            "LicenseCheckForEditIsEnabled" => true,
            "UserCanNotWriteRelative" => false,
            "SupportsRename" => true,
            "BreadcrumbBrandName" =>"AirSend",
            "BreadcrumbBrandUrl" => "https://www.airsend.io",
            "HostEditUrl" => $this->getHostEditUrl($claims)
        );

        //Don't show edit button for binary document formats, when
        //invoked from a viewer, as conversion from viewer fails
        if($ext == "doc" || $ext == "ppt" || $ext == "xls"){
            $infoJson['ReadOnly'] = true;
        }

        $this->logger->info(__FUNCTION__ . " : " . print_r($infoJson,true));


        $response->withHeader('Content-type','application/json;charset=UTF-8');
        $response->getBody()->write(Json::encode($infoJson));

    }


    /**
     * @param User $user
     * @param string $path
     * @return string
     */
    private function getHostEditUrl($claims): string
    {
        return rtrim($this->config->get("/app/server/baseurl"),'/') .'/wopi.edit?fspath='.$claims['path'] . "&token=".$claims['token'];
    }

    /**
     *
     * Send the requested file
     *
     * @param array $claims
     * @param Response $response
     * @return void
     */
    private function downloadFile(array $claims, Response $response): void
    {

        try {
            $user = $this->dataController->getUserById((int)$claims['user_id']);
            $path = $claims['path'];

            $downloadResource = $this->fOps->download($path, null, $user, 'local');

            $downloadResource->handle($response);

        } catch (ASException $e) {
            $this->logger->error(__FUNCTION__ . " Exception " .$e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @param array $claims
     * @return void
     * @throws SecurityException
     * @throws UnknownPolicyEntityException
     * @throws UnknownResourceException
     * @throws UnknownUserException
     */
    private function uploadFile(Request $request, array $claims): void
    {
        $path = $claims['path'];
        $userId = (int)$claims['user_id'];

        $user = $this->dataController->getUserById($userId);

        $extension = '';
        if (preg_match('/\.([^.]+)$/', $path, $matches)) {
            $extension = $matches[1];
        }
        $tmpName = Directories::tmp('tmp_wopi_' . bin2hex(random_bytes(8)) . '.' . $extension);

        //Ideally this should be getting this from body instead of php input.
        file_put_contents($tmpName, file_get_contents('php://input'));
        $this->logger->info(__FUNCTION__ . "Saved size = ". filesize($tmpName));

        if (!preg_match('/^(.*)\/([^\/]+)$/', $path, $matches)) {
            $this->logger->error(__FUNCTION__ . " Failed saving file $path. Invalid Path.");
            return;
        }

        $parentPath = $matches[1];
        $name = $matches[2];

        try {
            $this->fOps->upload($parentPath, $name, $tmpName, $user);
        } catch (DatabaseException | InvalidArgumentException | FSOpServerException | FSOpException | FSNotFoundException | FSAuthorizationException $e) {
            $this->logger->error(__FUNCTION__ . " Failed saving file $path");
        }

        unlink($tmpName);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $claims
     * @return string|null
     */
    private function lockFile(Request $request, Response $response, array $claims): void
    {
        $path = $claims['path'];
        $user_id = (int)$claims['user_id'];
        $expires = date("Y-m-d H:i:s", strtotime('+1 minute'));
        if (!empty($lock = $this->lockOps->lock($user_id, $path, "WOPI", $expires))) {
            // Got the lock.
            $this->logger->info("Lock got successfully");
        } else {
            $this->logger->info("Lock failed");
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $claims
     * @return string|null
     * @throws DatabaseException
     */
    private function unlockFile(Request $request, Response $response, array $claims): void
    {
        $this->logger->info(__FUNCTION__ );
        $path = $claims['path'];
        $user_id = (int)$claims['user_id'];

        $this->lockOps->unlock($user_id, $path, "WOPI");
    }

    /**
     *
     * Get lock id for WOPI
     *
     * @param Request $request
     * @param Response $response
     * @param array $claims
     * @return string|null
     * @throws DatabaseException
     */
    private function getLock(Request $request, Response $response, array $claims): void
    {
        $this->logger->info(__FUNCTION__ );
        $path = $claims['path'];
        $user_id = (int)$claims['user_id'];

        $lockId = "";
        $lock = $this->lockOps->getLock($path);
        if (!empty($lock) && $lock->userId() == $user_id && $lock->context() == "WOPI") {
            $lockId = $lock->id();
        }
        $this->logger->info(__FUNCTION__ . " : Lock ID = $lockId");
        $response->withHeader("X-WOPI-Lock", $lockId);
    }

    /**
     *
     * Refreshing lock
     *
     * @param Request $request
     * @param Response $response
     * @param array $claims
     * @return void
     * @throws DatabaseException
     */
    private function refreshLock(Request $request, Response $response, array $claims): void
    {
        $this->logger->info(__FUNCTION__ );
        $path = $claims['path'];
        $user_id = (int)$claims['user_id'];
        $expires = date("Y-m-d H:i:s", strtotime('+1 minute'));
        $clientLockId = (string) $response->getHeader('HTTP_X_WOPI_LOCK');
        $this->logger->info(__FUNCTION__ . " : Refreshing $clientLockId");

        $lock = $this->lockOps->getLockById((int)$clientLockId);
        if (!empty($lock)) {
            $this->logger->debug(__FUNCTION__ . " : Expiry " . $lock->expiry() . " --> " . $expires);
            $lock->setExpiry($expires);
            $this->lockOps->update($lock);
        }
        else {
            $this->logger->info(__FUNCTION__ . " : Lock $clientLockId not found");
        }
    }


}
