<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

namespace CodeLathe\Core\Managers;

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\InvalidSuccessfulHttpCodeException;
use CodeLathe\Core\Serializers\JSONSerializer;
use CodeLathe\Core\Utility\App;
use CodeLathe\Core\Utility\JsonOutput;
use CodeLathe\Service\Database\DatabaseService;
use CodeLathe\Service\EventDispatcher\EventDispatcherService;
use CodeLathe\Service\Mailer\MailerServiceInterface;
use CodeLathe\Service\MQ\MQService;
use CodeLathe\Service\SMS\SMSServiceInterface;
use CodeLathe\Service\Storage\StorageService;
use CodeLathe\Service\Zoo\ZooService;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request as Request;
use Symfony\Contracts\EventDispatcher\Event;

class DevManager
{
    protected $container;

    /**
     * @var MailerServiceInterface
     */
    protected $mailer;

    /**
     * @var SMSServiceInterface
     */
    protected $smsService;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container, MailerServiceInterface $mailer, SMSServiceInterface $smsService, DataController $dataController, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->mailer = $mailer;
        $this->smsService = $smsService;
        $this->dataController = $dataController;
        $this->logger = $logger;
    }

    private function checkRedis()
    {
        $cache = $this->container->get(CacheItemPoolInterface::class);
        $item = $cache->getItem('airsend.dev.redis');
        if (!$item->isHit()) {
            $item->set('yay');
            $cache->save($item);
            $item = $cache->getItem('airsend.dev.redis');
            if (!$item->isHit()) {
                return false;
            }
        }
        $resp = $item->get();
        if ($resp == 'yay')
            return true;

        return false;
    }

    private function checkMQ()
    {
        $mq = $this->container->get(MQService::class);
        $mqtopic = $mq->newTopic('airsend.dev.topic');
        $mq->publish($mqtopic, 'Hello via MQ Service ' . microtime(true));
        return true;
    }

    private function checkDB()
    {
        $db = $this->container->get(DatabaseService::class);

        $tableExists = false;
        try {
            $result = $db->select("SELECT * from versions");
            foreach ($result as $row) {
                $this->container->get(LoggerInterface::class)->debug($row['id'] . ' notes: ' . $row['notes'] . ' on ' . $row['created_on']);
            }
        } catch (\Exception $e) {
            //$this->container->get(LoggerInterface::class)->erro('Failed to connect to DB: '.$e->getMessage());
            return false;
        }

        return true;
    }

    private function checkEvents()
    {
        $this->container->get(EventDispatcherService::class)->addListener('foo.action', function (Event $event) {
            $this->container->get(LoggerInterface::class)->debug("Event Fired " . print_r($event, true));
        });

        $this->container->get(EventDispatcherService::class)->dispatch(new Event(), 'foo.action');
        return true;
    }

    private function checkZooKeeper()
    {
        if (!$this->container->get(ZooService::class)->exists('/airsend.dev')) {
            $setPath = $this->container->get(ZooService::class)->create('/airsend.dev', '1');
            if ($setPath == '/airsend.dev') {
                return true;
            }
        } else {
            $value = $this->container->get(ZooService::class)->get('/airsend.dev');
            if ($value == '1') {
                if (!$this->container->get(ZooService::class)->exists('/airsend.dev/child1')) {
                    $setPath = $this->container->get(ZooService::class)->create('/airsend.dev/child1', '1');
                }
                if (!$this->container->get(ZooService::class)->exists('/airsend.dev/child2')) {
                    $setPath = $this->container->get(ZooService::class)->create('/airsend.dev/child2', '1');
                }
                $children = $this->container->get(ZooService::class)->getChildren('/airsend.dev');
                $this->container->get(LoggerInterface::class)->debug('ZooKeeper: ' . print_r($children, true));
                if (count($children) > 0)
                    return true;
            }
        }

        return false;
    }

    public function check(Request $request, Response $response)
    {

        if ($this->container->get(ConfigRegistry::class)['/app/mode'] == 'prod') {
            return (new JSONSerializer(false))->withHTTPCode(404)->withError('Resource not found')->write($response);
        }

        $this->logger->debug('Running the dev system check...');

        $response->getBody()->write("<!DOCTYPE html><html> <style>
                            body {background-color: #FAFBFB;font-family: Calibri, Consolas, 'Lucida Console', Helvetica}
                            h1   {color: darkslategrey;}
                            h2   {color: darkmagenta;}
                            h3   {color: dimgrey;}
                            
                            </style>");

        $app = $this->container->get(App::class);
        $response->getBody()->write('<h1>AirSend v' . $app->version() . '</h1>');
        $response->getBody()->write('<h2> ** DEV Mode only ** </h2>');

        $statusArray = array();
        $statusArray['PHP Ext: Curl'] = extension_loaded('curl');
        $statusArray['PHP Ext: JSON'] = extension_loaded('json');
        $statusArray['PHP Ext: redis'] = extension_loaded('redis');
        $statusArray['PHP Ext: rdkafka'] = extension_loaded('rdkafka');
        $statusArray['PHP Ext: zookeeper'] = extension_loaded('zookeeper');
        $statusArray['Service: Redis'] = $this->checkRedis();
        $statusArray['Service: MQ'] = $this->checkMQ();
        $statusArray['Service: DB'] = $this->checkDB();
        $statusArray['Service: Dispatcher'] = $this->checkEvents();
        $statusArray['Service: ZooKeeper'] = $this->checkZooKeeper();

        $response->getBody()->write("<h3>Checks</h3>");
        $response->getBody()->write("<ul>");
        foreach ($statusArray as $key => $value) {
            $response->getBody()->write('<li>' . $key . ' is ' . ($value ? 'OK' : 'FAILED') . '</li>');
        }
        $response->getBody()->write("</ul>");

        $morecommands = "
        <h3>Info</h3>
        <a href='/api/v1/dev.info'>PHP Info</a>";
        $response->getBody()->write($morecommands);

        $restcommands = "
        <br/><h3>API Scratchpad</h3>
        GET <a href='/api/v1/dev.storage'>/dev.storage test</a><br/>";


        $response->getBody()->write($restcommands);

        $response->getBody()->write("</html>");
        return $response;
    }

    public function storage(Request $request, Response $response)
    {

        $name = $this->container->get(StorageService::class)->name();
        $this->container->get(StorageService::class)->test();
        $response->getBody()->write("Storage tests complete. Look logs for results:");
        return $response;
    }

    public function info(Request $request, Response $response)
    {
        phpinfo();
        return $response;
    }

    public function sendEmail(Request $request, Response $response)
    {

        $message = $this->mailer
            ->createMessage('Jeferson Almeida <jefersonparanaense@gmail.com>')
            ->subject('You have a new plain message on channel Airsend Dev')
            ->from('madhan')
            ->html('<h1>You have a new message</h1><p>Hey Jeff, how are you?</p>')
            ->plain("You have a new message \n ------------------ \n Hey Jeff, how are you?");

        $this->mailer->send($message);
        $response->getBody()->write('sending email...');
        return $response;
    }

    public function sendSms(Request $request, Response $response)
    {

        $this->smsService->send('+5541988878250', 'Test SMS to jeff...');
        $response->getBody()->write('sending sms...');
        return $response;

    }

    /**
     * This endpoint is just a POC for email receiving.
     *
     * To check it, you need to setup Ngrok, configure mailgun to post the email to your domains, and then send an
     * email to any address on the airsendmail.com domain. Examples:
     * - madhan@airsendmail.com
     * - jeff@airsendmail.com
     * - jonsnow@airsendmail.com
     *
     * You can check the results inside the `/scratch/receivedemail` folder, that is a temporary repository for received
     * emails.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function receiveEmail(Request $request, Response $response)
    {

        // TODO - Convert this small parser to a reusable service..., maybe include it on the Mailer service...
        // The parser can be completely unit tested...
        // Te entrypoint for this service should be a method `parse` that receives the request as parameter.
        // Is important to use an adapter/strategy pattern to deal with it (for now, create a driver for mailgun)

        $dir = dirname(__DIR__, 3) . '/scratch/receivedemail';
        $file = 'mail_' . date('YmdHis') . '.txt';

        $lines = explode("&", $request->getBody()->getContents());
        $data = [];
        foreach ($lines as $line) {
            [$key, $content] = explode("=", $line);
            $data[urldecode($key)] = urldecode($content);
        }

        file_put_contents($dir . '/' . $file, print_r($data, true));

        $response->withStatus(204);
        return $response;
    }

    /**
     * This route just returns the request payload inside the `requestPayload` key
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidSuccessfulHttpCodeException
     */
    public function requestEcho(Request $request, Response $response): Response
    {
        return JsonOutput::success()
            ->withContent('contentType', $request->getHeader('content-type'))
            ->withContent('requestPayload', $request->getParsedBody())
            ->write($response);
    }

}