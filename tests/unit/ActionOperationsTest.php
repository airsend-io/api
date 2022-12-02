<?php

/**
 * Class ActionTest
 * @group action
 */
use Codeception\Test\Unit;
use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\ActionOpException;
use CodeLathe\Core\Exception\ChannelMalformedException;
use CodeLathe\Core\Exception\ChannelOpException;
use CodeLathe\Core\Exception\ChatOpException;
use CodeLathe\Core\Exception\DatabaseException;
use CodeLathe\Core\Exception\NotImplementedException;
use CodeLathe\Core\Managers\Action\ActionOperations;
use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\Files\FileController;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Objects\Channel;
use CodeLathe\Core\Objects\File;
use CodeLathe\Core\Objects\Folder;
use CodeLathe\Core\Objects\User;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

defined('CL_AS_ROOT_DIR') or define('CL_AS_ROOT_DIR', realpath(__DIR__ . '/../..'));

class ActionOperationsTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Channel
     */
    protected $channel;


    /**
     * @throws Exception
     */

    protected function _before()
    {
        $configRegistry = new ConfigRegistry();
        $containerIniter = require CL_AS_ROOT_DIR.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Init.php';
        $this->container = $containerIniter($configRegistry);

        // ... Create a random user
        $uo = $this->container->get(UserOperations::class);
        $this->user = $uo->createUser(time().'@gg.com','447700900899', 'password', 'UnitTest Man',
            User::ACCOUNT_STATUS_ACTIVE, User::USER_ROLE_EDITOR, User::APPROVAL_STATUS_APPROVED);

        $co = $this->container->get(ChannelOperations::class);
        $channelName = "CL".time();
        $this->channel = $co->createChannel($this->user, $channelName);
    }

    protected function _after()
    {
    }

    /**
     * @throws ActionOpException
     * @throws ChannelOpException
     * @throws ChatOpException
     * @throws DatabaseException
     */
    public function testCreateActionOperations()
    {
        /** @var ActionOperations $actionOp */
        $actionOp = $this->container->get(ActionOperations::class);

        $channelId = $this->channel->getId();
        $actionName = "Review Data";
        $desc    = "Review Data";
        $actionType    = 2;
        $actionStatus = 0;
        $dueOn   = 2019-10-20;
        $createdBy = $this->user->getId();
        $userId = Array($this->user->getId());


        $obj = $actionOp->createAction($channelId, $actionName, $desc, $actionType, $actionStatus, $dueOn, $createdBy, $userId);
        $this->assertInstanceOf(\CodeLathe\Core\Objects\Action::class, $obj);
        $this->assertEquals($obj->getName(), $actionName);
        $this->assertEquals($obj->getActionType(), $actionType);
        $this->assertEquals($obj->getActionStatus(), $actionStatus);

    }

    public function testGetActionOperations()
    {
        $dc = new DataController($this->container);
        $actionOp = $this->container->get(ActionOperations::class);

        $channelId = $this->channel->getId();
        $actionName = "Review Data";
        $desc    = "Review Data";
        $actionType    = 2;
        $actionStatus = 0;
        $dueOn   = 2019-10-20;
        $createdBy = $this->user->getId();
        $userId = Array($this->user->getId());


        $obj = $actionOp->createAction($channelId, $actionName, $desc, $actionType, $actionStatus, $dueOn, $createdBy, $userId);
        $this->assertInstanceOf(\CodeLathe\Core\Objects\Action::class, $obj);
        $this->assertEquals($obj->getName(), $actionName);
        $this->assertEquals($obj->getActionType(), $actionType);
        $this->assertEquals($obj->getActionStatus(), $actionStatus);

        //getAction
        $actionId = $obj->getId();
        $requestedBy = $this->user->getId();

        $obj = $actionOp->getAction($actionId, $requestedBy);
        $this->assertInstanceOf(\CodeLathe\Core\Objects\Action::class, $obj);
        $this->assertEquals($obj->getName(), $actionName);
        $this->assertEquals($obj->getActionType(), $actionType);
        $this->assertEquals($obj->getActionStatus(), $actionStatus);

    }

    /**
     * @throws ActionOpException
     * @throws ChannelOpException
     * @throws ChatOpException
     * @throws DatabaseException
     * @throws ChannelMalformedException
     * @throws NotImplementedException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testGetActionsOperations()
    {
        /** @var ActionOperations $actionOp */
        $actionOp = $this->container->get(ActionOperations::class);

        $channelId = $this->channel->getId();
        $actionName = "Review Data";
        $desc    = "Review Data";
        $actionType    = 2;
        $actionStatus = 0;
        $dueOn   = '2019-10-20';
        $createdBy = $this->user->getId();
        $userId = array($this->user->getId());


        $obj = $actionOp->createAction($channelId, $actionName, $desc, $actionType, $actionStatus, $dueOn, $createdBy, $userId);
        $this->assertInstanceOf(\CodeLathe\Core\Objects\Action::class, $obj);
        $this->assertEquals($obj->getName(), $actionName);
        $this->assertEquals($obj->getActionType(), $actionType);
        $this->assertEquals($obj->getActionStatus(), $actionStatus);

        $actionName_1 = "Update Data";
        $desc_1    = "Update Data";
        $actionType_1    = 3;
        $actionStatus_1 = 0;
        $dueOn_1  = 2019-10-20;
        $createdBy_1 = $this->user->getId();
        $userId_1 = array($this->user->getId());


        $obj = $actionOp->createAction($channelId, $actionName_1, $desc_1, $actionType_1, $actionStatus_1, $dueOn_1, $createdBy_1, $userId_1);
        $this->assertInstanceOf(\CodeLathe\Core\Objects\Action::class, $obj);
        $this->assertEquals($obj->getName(), $actionName_1);
        $this->assertEquals($obj->getActionType(), $actionType_1);
        $this->assertEquals($obj->getActionStatus(), $actionStatus_1);

        //Get all actions for this channel
        $requestedBy = $this->user->getId();

        $obj = $actionOp->getActions($requestedBy, $channelId, $requestedBy);
        $this->assertCount(2,$obj);
        //TODO: Write More assertions

    }

    public function testUpdateActionOperations()
    {

        $dc = new DataController($this->container);
        $actionOp = $this->container->get(ActionOperations::class);

        $channelId = $this->channel->getId();
        $actionName = "Review Data";
        $desc    = "Review Data";
        $actionType    = 2;
        $actionStatus = 0;
        $dueOn   = 2019-10-20;
        $createdBy = $this->user->getId();
        $userId = Array($this->user->getId());


        $obj = $actionOp->createAction($channelId, $actionName, $desc, $actionType, $actionStatus, $dueOn, $createdBy, $userId);
        $this->assertInstanceOf(\CodeLathe\Core\Objects\Action::class, $obj);
        $this->assertEquals($obj->getName(), $actionName);
        $this->assertEquals($obj->getActionType(), $actionType);
        $this->assertEquals($obj->getActionStatus(), $actionStatus);

        //Update the actionStatus and actionName

        $actionId = $obj->getId();
        $updatedActionName = "Reviewed Data";
        $updatedActionStatus = 1;

        $obj = $actionOp->updateAction($actionId, $updatedActionName, $desc, $actionType, $updatedActionStatus, $dueOn, $createdBy, $userId);
        $this->assertInstanceOf(\CodeLathe\Core\Objects\Action::class, $obj);
        $this->assertEquals($obj->getName(), $updatedActionName);
        $this->assertEquals($obj->getActionType(), $actionType);
        $this->assertEquals($obj->getActionStatus(), $updatedActionStatus);



    }

}
