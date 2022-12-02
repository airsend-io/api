<?php

use Codeception\Test\Unit;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Messaging\EventManager;
use CodeLathe\Core\Messaging\Events\ASEvent;

defined('CL_AS_ROOT_DIR') or define('CL_AS_ROOT_DIR', realpath(__DIR__ . '/../..'));

class UnitTestEvent extends ASEvent
{

    public const NAME = 'unittest.event';

    protected  $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function setPayload(string $message)
    {
        $this->message = $message;
    }
    public function getPayload(): string
    {
        return $this->message;
    }


    /**
     * This method is to get the base event Name
     * @return string
     */
    static function eventName () : string
    {
        // TODO: Implement eventName() method.
        return static::NAME;
    }

    /**
     * Get array representation of the event payload
     * @return array
     */
    function getPayloadArray () : array
    {
        return [$this->message];
    }
}

/**
 * Class EventsTest
 * @group event
 */

class EventsTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $container;

    static $foregroundEventReceived;

    static $backgroundEventReceived;


    static $eventMessage;

    /**
     * @throws Exception
     */
    protected function _before()
    {
        $configRegistry = new ConfigRegistry();
        $containerIniter = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Init.php';
        $this->container = $containerIniter($configRegistry);
    }

    protected function _after()
    {
    }

    public function foregroundCallBackMethod(ASEvent $event)
    {
        static::$foregroundEventReceived = true;

        $this->assertInstanceOf(UnitTestEvent::class, $event);

        $this->assertEquals(static::$eventMessage, $event->getPayload());
    }


    public function testForegroundEvent()
    {
        static::$foregroundEventReceived = false;

        $eventMgr = $this->container->get(EventManager::class);

        $eventMgr->addListener(UnitTestEvent::foregroundEventName(), [$this, 'foregroundCallBackMethod']);

        static::$eventMessage = "Unit test message";

        $event = new UnitTestEvent(static::$eventMessage);
        $eventMgr->publishEvent($event);

        // Foreground event means we should have had the event by now
        $this->assertEquals(true, static::$foregroundEventReceived);
    }


}