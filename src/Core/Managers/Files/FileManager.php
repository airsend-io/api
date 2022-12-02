<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers\Files;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Data\SearchDataController;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\FSAuthorizationException;
use CodeLathe\Core\Exception\FSNotFoundException;
use CodeLathe\Core\Exception\FSOpException;
use CodeLathe\Core\Exception\FSOpServerException;
use CodeLathe\Core\Exception\InvalidFailedHttpCodeException;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Exception\SecurityException;
use CodeLathe\Core\Exception\ValidationErrorException;
use CodeLathe\Core\Managers\ManagerBase;
use CodeLathe\Core\Objects\FileSystemObject;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Serializers\CallbackStream;
use CodeLathe\Core\Serializers\FileSerializer;
use CodeLathe\Core\Serializers\UrlStreamSerializer;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Core\Utility\Memoizer;
use CodeLathe\Core\Utility\ObjectFactory\NormalizedObjectFactory;
use CodeLathe\Core\Utility\RequestValidator;
use CodeLathe\Core\Utility\SafeFile;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Storage\Exceptions\StorageServiceException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;
use Throwable;

class FileManager extends ManagerBase
{
    protected $logger;
    protected $fops;
    protected $dc;

    /**
     * @var UrlStreamSerializer
     */
    protected $urlStreamSerializer;

    /**
     * @var ConfigRegistry
     */
    protected $config;

    /**
     * @var NormalizedObjectFactory
     */
    protected $objectFactory;

    /**
     * @var SearchDataController
     */
    protected $searchDataController;

    public function __construct(LoggerInterface $logger,
                                DataController $dataController,
                                FileOperations $fops,
                                UrlStreamSerializer $urlStreamSerializer,
                                ConfigRegistry $config,
                                NormalizedObjectFactory $objectFactory,
                                SearchDataController $searchDataController)
    {
        $this->logger = $logger;
        $this->fops = $fops;
        $this->dc = $dataController;
        $this->urlStreamSerializer = $urlStreamSerializer;
        $this->config = $config;
        $this->objectFactory = $objectFactory;
        $this->searchDataController = $searchDataController;
    }

    /**
     * @param string $parentpath
     * @param int $chunkStart
     * @param bool $finalChunk
     * @param $uploadedFile
     * @param User $user
     * @param float $sessionid
     * @throws DatabaseException
     * @throws FSAuthorizationException
     * @throws FSNotFoundException
     * @throws FSOpException
     * @throws FSOpServerException
     * @throws InvalidArgumentException
     * @throws SecurityException
     */
    protected function handleUpload(string $parentpath, int $chunkStart, bool $finalChunk, $uploadedFile, User $user, float $sessionid): void
    {

        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8));// see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('tmp_upload_%s.%0.8s', $basename, $extension);
        $phyFile = CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'scratch' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $filename;
        $uploadedFile->moveTo($phyFile);

        try {
            $this->fops->upload($parentpath,
                $uploadedFile->getClientFilename(),
                $phyFile,
                $user,
                $chunkStart,
                $finalChunk,
                $sessionid);
        } catch (FSOpException | DatabaseException | FSAuthorizationException | FSNotFoundException | FSOpServerException $e) {
            throw $e;
        } finally {
            SafeFile::unlink($phyFile);
        }
    }

    /**
     * Uploads a File Resource
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws SecurityException
     * @throws ValidationErrorException
     */
    public function upload(Request $request, Response $response)
    {

        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();
        if (!RequestValidator::validateRequest(['fspath', 'start', 'complete'], $params, $response)) {
            return $response;
        }

        // handle single input with single file upload
        $uploadedFiles = $request->getUploadedFiles();
        if (count($uploadedFiles) <= 0) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " WebServer File Upload Failure, no files found : " . $params['fspath']);
            return JsonOutput::error("Upload Failure")->write($response);
        }

        $chunkStart = (int)($params["start"] ?? 0);
        $chunkComplete = (bool)($params["complete"] ?? true);

        if (!isset($uploadedFiles['file'])) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " WebServer File Upload Failure, bad file input param name, use 'file' : " . $params['fspath']);
            return JsonOutput::error("Upload Failure")->write($response);
        }

        $sessionid = $request->getAttribute('sessionid');

        $uploadedFile = $uploadedFiles['file'];
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " WebServer File Upload Failure, Error Code: " . $uploadedFile->getError() . " : " . $params['fspath']);
            JsonOutput::error('Upload failed', 500)->write($response);
        }

        try {
            $this->handleUpload($params["fspath"], $chunkStart, $chunkComplete, $uploadedFile, $user, $sessionid);
        } catch (FSAuthorizationException $e) {
            return JsonOutput::error('Unauthorized', 403)->write($response);
        } catch (FSNotFoundException $e) {
            return JsonOutput::error('Parent path not found', 404)->write($response);
        } catch (FSOpException $e) {
            return JsonOutput::error($e->getMessage())->write($response);
        } catch (FSOpServerException $e) {
            return JsonOutput::error($e->getMessage(), 500)->write($response);
        }

        return JsonOutput::success()->withContent("path", $params["fspath"] . '/' . $uploadedFile->getClientFilename())->write($response);

    }

    /**
     * Downloads a file
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException|DatabaseException
     */
    public function download(Request $request, Response $response)
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();
        if (!RequestValidator::validateRequest(['fspath', 'versionid'], $params, $response)) {
            return $response;
        }

        $versionId = null;
        if (isset($params['versionid'])) {
            $versionId = $params['versionid'];
        }

        try {
            $downloadResource = $this->fops->download($params["fspath"], $versionId, $user);
        } catch (FSAuthorizationException $e) {
            return JsonOutput::error("You're not allowed to access this path", 403)->write($response);
        } catch (FSOpException $e) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " Download Failure : " . $params['fspath']);
            return JsonOutput::error("Storage Error: {$e->getMessage()}", 404)->write($response);
        }

        try {
            return $downloadResource->handle($response);
        } catch (Exception $e) {
            return JsonOutput::error("Internal Storage Error", 500)->write($response);
        }

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws ValidationErrorException
     */
    public function internalDownload(Request $request, Response $response)
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();
        if (!RequestValidator::validateRequest(['fspath', 'versionid'], $params, $response)) {
            return $response;
        }

        $versionId = $params['versionid'] ?? null;

        try {
            $downloadResource = $this->fops->download($params["fspath"], $versionId, null, 'local');
        } catch (FSAuthorizationException $e) {
            return JsonOutput::error("You're not allowed to access this path", 403)->write($response);
        } catch (FSOpException $e) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " Download Failure : " . $params['fspath']);
            return JsonOutput::error("Storage Error")->write($response);
        }

        try {
            return $downloadResource->handle($response);
        } catch (Exception $e) {
            return JsonOutput::error("Internal Storage Error", 500)->write($response);
        }
    }

    /**
     * @param string $mainPath
     * @param $uploadedFile
     * @throws DatabaseException
     * @throws FSNotFoundException
     * @throws FSOpException
     * @throws SecurityException
     */
    protected function handleSideCarUpload(string $mainPath, $uploadedFile): void
    {

        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8));// see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('tmp_upload_%s.%0.8s', $basename, $extension);
        $phyFile = CL_AS_ROOT_DIR . DIRECTORY_SEPARATOR . 'scratch' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $filename;
        $uploadedFile->moveTo($phyFile);

        try {
            $this->fops->uploadSideCar($mainPath, $phyFile);
        } catch (FSNotFoundException | FSOpException $e) {
            throw $e;
        } finally {
            SafeFile::unlink($phyFile);
        }

    }


    public function internalSidecarUpload(Request $request, Response $response)
    {
        if (empty($admin = $this->requireValidAdmin($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();
        if (!RequestValidator::validateRequest(['fspath', 'start', 'complete'], $params, $response)) {
            return $response;
        }

        // handle single input with single file upload
        $uploadedFiles = $request->getUploadedFiles();
        if (count($uploadedFiles) <= 0) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " WebServer File Upload Failure, no files found : " . $params['fspath']);
            return JsonOutput::error("Upload Failure")->write($response);
        }

        if (!isset($uploadedFiles['file'])) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " WebServer File Upload Failure, bad file input param name, use 'file' : " . $params['fspath']);
            return JsonOutput::error("Upload Failure")->write($response);
        }

        $uploadedFile = $uploadedFiles['file'];

        try {
            $this->handleSideCarUpload($params["fspath"], $uploadedFile);
        } catch (FSNotFoundException $e) {
            return JsonOutput::error("Main file was not found.", 404)->write($response);
        } catch (FSOpException $e) {
            return JsonOutput::error("Storage error", 400)->write($response);
        }

        return JsonOutput::success()->withContent("path", $params["fspath"])->write($response);

    }


    /**
     * Deletes a File Resource
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function delete(Request $request, Response $response)
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();
        if (!RequestValidator::validateRequest(['fspath'], $params, $response)) {
            return $response;
        }

        try {
            $this->fops->delete($params["fspath"], $user);
        } catch (FSAuthorizationException $e) {
            return JsonOutput::error("You're not authorized to delete this file", 403)->write($response);
        } catch (FSOpException | FSOpServerException | StorageServiceException $e) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " Delete Failure : " . $params['fspath']);
            return JsonOutput::error("Storage Error")->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    /**
     * Create a new Folder Resource
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function create(Request $request, Response $response)
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        };


        $params = $request->getParsedBody();
        if (!RequestValidator::validateRequest(['fsparent', 'fsname'], $params, $response)) {
            return $response;
        }

        try {
            $this->fops->create($params["fsparent"], $params['fsname'], $user);
        } catch (FSOpException | FSAuthorizationException $e) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " Folder Create Failure : " . $params['fsparent'] . " " . $params['fsname']);
            return JsonOutput::error($e->getMessage())->write($response);
        }

        return JsonOutput::success()->write($response);
    }


    /**
     * Lists contents of a Folder Resource
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidArgumentException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws NotImplementedException
     * @throws ValidationErrorException
     * @throws \CodeLathe\Core\Exception\ChannelMalformedException
     */
    public function list(Request $request, Response $response)
    {

        if (empty($loggedUser = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();
        $fieldNameMapping = ['fspath' => 'fspath_allow_empty'];
        if (!RequestValidator::validateRequest(['fspath', 'start', 'limit', 'scope'], $params, $response, $fieldNameMapping)) {
            return $response;
        }

        // root params ------
        if ($params['fspath'] === null) {
            return JsonOutput::error('fspath is required')->write($response);
        }
        $fspath = rtrim(trim($params['fspath']), '/');

        $inDepth = (bool)($params['in_depth'] ?? false);

        $ignoreFolders = (bool)($params['ignore_folders'] ?? false);

        // filters ------
        $typeFilter = $params['type'] ?? null; // media or docs
        if ($typeFilter !== null && !in_array($typeFilter, ['media', 'docs'])) {
            return JsonOutput::error('Supported types are media or docs', 422)->write($response);
        }

        // sorting --------
        $sortBy = trim($params['sort_by'] ?? 'updated_on');
        if (!in_array($sortBy, ['updated_on', 'name'])) {
            return JsonOutput::error('Invalid sort_by.', 422)->write($response);
        }
        $sortDesc = (bool)($params['sort_desc'] ?? ($sortBy === 'updated_on'));

        // infinite pagination -----
        // cursor is an entry inside the requested fspath
        $cursor = $params['cursor'] ?? null;
        $regex = '/^' . preg_quote($fspath, '/') . ($inDepth ? '\/.+$/' : '\/[^\/]+$/');

        if ($cursor !== null) {

            // simply validate cursor format (it still can be invalid).
            if (!preg_match($regex, $cursor)) {
                return JsonOutput::error('Invalid cursor.', 422)->write($response);
            }

        }

        // means get x files after the cursor (optional). When both limits are set, the cursor file is included
        $limitAfter = isset($params['limit_after']) ? (int)($params['limit_after']) : null;

        // means get x files before the cursor (optional). When both limits are set, the cursor file is included
        $limitBefore = isset($params['limit_before']) ? (int)($params['limit_before']) : null;

        // search -------
        $searchQuery = $params['search'] ?? '';

        // get the paginated result
        try {

            $items = $this->fops->list(
                $fspath,
                $loggedUser,
                $searchQuery,
                $sortBy,
                $sortDesc,
                $cursor,
                $limitBefore,
                $limitAfter,
                $inDepth,
                $ignoreFolders,
                $typeFilter
            );
        } catch (FSAuthorizationException $e) {
            return JsonOutput::error("You're not allowed to list this folder", 403)->write($response);
        } catch (FSOpException $e) {
            return JsonOutput::error('Invalid request: ' . $e->getMessage())->write($response);
        }

        $normalizedEntries = array_map(function (FileSystemObject $item) {
            return $this->objectFactory->normalizedObject($item);
        }, $items);

        if (empty($fspath) || $fspath === '/cf') {
            $canUpload = $canCreateFolder = false;
        } else {
            $translatedPath = Memoizer::memoized([$this->fops, 'translatePath'])($fspath);
            $canUpload = $loggedUser->can('upload', $translatedPath);
            $canCreateFolder = $loggedUser->can('createFolder', $translatedPath);
        }

        return JsonOutput::success()
            ->withContent("total", count($normalizedEntries))
            ->withContent("canupload", $canUpload)
            ->withContent("cancreatefolder", $canCreateFolder)
            ->withContent("files", $normalizedEntries)
            ->write($response);
    }

    /**
     * Copies a File Resource to another location
     *
     * @param Request $request
     * @param Response $response
     * @param bool $isCopy
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    protected function copyOrMove(Request $request, Response $response, bool $isCopy)
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getParsedBody();
        if (!RequestValidator::validateRequest(['fsfrompath', 'fstopath'], $params, $response)) {
            return $response;
        }

        try {
            $this->fops->copyOrMove($params["fsfrompath"], $params["fstopath"], $user, $isCopy);
        } catch (FSOpException $e) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " File Copy/Move Failure : {$params["fsfrompath"]} --> {$params["fstopath"]}");
            return JsonOutput::error($e->getMessage())->write($response);
        }

        return JsonOutput::success()->write($response);
    }

    public function move(Request $request, Response $response)
    {
        return $this->copyOrMove($request, $response, false);
    }

    /**
     * Moves a File Resource to another location
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function copy(Request $request, Response $response)
    {
        return $this->copyOrMove($request, $response, true);
    }

    /**
     * Returns Info related to a specific file resource
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function info(Request $request, Response $response)
    {

        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();
        if (!RequestValidator::validateRequest(['fspath'], $params, $response)) {
            return $response;
        }

        //$item = $this->fops->infolf($params["fspath"], (int)$userid);
        try {
            $item = $this->fops->info($params["fspath"], $user);
        } catch (FSAuthorizationException $e) {
            return JsonOutput::error("You're not allowed to access this path", 403)->write($response);
        } catch (FSNotFoundException $e) {
            return JsonOutput::error("Path not found", 404)->write($response);
        }
//        catch (Throwable $e) {
//            $this->logger->error(__CLASS__.":".__FUNCTION__ . " File Info Failure : ".$params["fspath"]);
//            return JsonOutput::error("Storage Error")->write($response);
//        }

        $normalizedItem = $this->objectFactory->normalizedObject($item);

        return JsonOutput::success()->withContent("file", $normalizedItem)->write($response);
    }

    /**
     * Lists the previous versions of a given File Resource
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function versions(Request $request, Response $response)
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();
        if (!RequestValidator::validateRequest(['fspath'], $params, $response)) {
            return $response;
        }

        try {
            $versions = $this->fops->versions($params["fspath"], $user);
        } catch (FSAuthorizationException $e) {
            return JsonOutput::error("You're not authorized to list versions for this file")->write($response);
        } catch (FSNotFoundException $e) {
            return JsonOutput::error("The requested file doesn't exist")->write($response);
        }

        return JsonOutput::success()->withContent("file", $versions)->write($response);
    }

    public function thumb(Request $request, Response $response)
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();
        $fieldNameMapping = ['width' => 'twidth', 'height' => 'theight'];
        if (!RequestValidator::validateRequest(['fspath', 'width', 'height'], $params, $response, $fieldNameMapping)) {
            return $response;
        }

        $width = (int)$params["width"];
        $height = (int)$params["height"];

        if (!$this->fops->validateThumbDimensions($width, $height)) {
            return JsonOutput::error("Invalid thumb dimension", 422)->write($response);
        }

        $sessionid = $request->getAttribute('sessionid');

        try {
            $downloadResource = $this->fops->thumb($params["fspath"], $width, $height, $user, $sessionid, 'local');
        } catch (FSAuthorizationException $e) {
            return JsonOutput::error("You're not authorized to access this file", 403)->write($response);
        } catch (FSNotFoundException $e) {
            return JsonOutput::error("File not found", 404)->write($response);
        } catch (FSOpException $e) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " Thumb Failure : " . $params['fspath']);
            return JsonOutput::error("Thumb Generation Error")->write($response);
        }

        return $downloadResource->handle($response);
    }

    public function synclist(Request $request, Response $response)
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        };

        $userid = $user->getId();

        $items = $this->fops->synclist((int)$userid);
        if ($items === false) {
            $this->logger->error(__CLASS__ . ":" . __FUNCTION__ . " File Sync List Failure for " . $userid);
            return JsonOutput::error("Invalid Path for Sync List")->write($response);
        }

        return JsonOutput::success()->withContent("synclist", $items)->write($response);
    }

    public function zip(Request $request, Response $response)
    {
        if (empty($user = $this->requireValidUser($request, $response))) {
            return $response;
        }

        $params = $request->getQueryParams();
        if (!RequestValidator::validateRequest(['fspath'], $params, $response)) {
            return $response;
        }

        // ... Send Callback that will be run by the response object

        // TODO - check this, looks like it's not behaving as a stream (it's downloading everything to the server before beginning the download
        $output = new CallbackStream(function () use ($params, $user) {
            $this->fops->sendZipCallback($params['fspath'], $user);
            return '';
        });

        return $response->withBody($output);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws DatabaseException
     * @throws InvalidFailedHttpCodeException
     * @throws InvalidSuccessfulHttpCodeException
     * @throws ValidationErrorException
     */
    public function createLink(Request $request, Response $response): Response
    {
        $user = $this->requireValidUser($request, $response);
        if (empty($user)) {
            return $response;
        }

        $params = $request->getParsedBody();
        if (!RequestValidator::validateRequest(['fspath'], $params, $response)) {
            return $response;
        }
        $fspath = $params['fspath'];

        // check if there is a short url already created for the file
        $shortUrlHash = $this->dc->findShortUrlHashForResource('File', $fspath);

        // if not, create it
        if ($shortUrlHash === null) {
            try {
                $shortUrl = $this->fops->createLink($fspath, $user);
            } catch (FSAuthorizationException $e) {
                return JsonOutput::error('Forbidden', 403)->write($response);
            } catch (Throwable $e) {
                return JsonOutput::error('Storage error')->write($response);
            }
        } else {
            $shortUrl = StringUtility::generateShortUrlFromHash($shortUrlHash);
        }

        return JsonOutput::success()->withContent('url', $shortUrl)->write($response);
    }

    public function deleteLink(Request $request, Response $response): Response
    {
        $user = $this->requireValidUser($request, $response);
        if (empty($user)) {
            return $response;
        }

        $params = $request->getParsedBody();
        if (!RequestValidator::validateRequest(['fspath'], $params, $response)) {
            return $response;
        }
        $fspath = $params['fspath'];

        // delete the short url (if exists)
        $this->dc->deleteShortUrlByResource('File', $fspath);

        // delete the public hash (if exists)
        $this->dc->deletePublicHashByResource('File', $fspath);

        return JsonOutput::success(204)->write($response);
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