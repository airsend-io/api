<?php

/**
 * Class ChannelCest
 * @group channel
 */
class ChannelCest
{

    const CHANNEL_NAME = 'New Test Channel';
    const TEST_USER = 'channelunittest@airsendmail.com';
    const TEST_USER_PASSWORD = 'password1';

    /**
     * @var string
     */
    protected $jwtToken = null;

    public function _before(ApiTester $I)
    {
        $I->loadFixture('channel/init');
    }

    /**
     * @param ApiTester $I
     */
    public function testChannelCreation(ApiTester $I)
    {
        $this->jwtToken = $I->loginUser(static::TEST_USER, static::TEST_USER_PASSWORD);

        // Create channel
        $I->sendPOST('/channel.create', ['channel_name' => static::CHANNEL_NAME]);
        $I->seeResponseCodeIsSuccessful();

        // assert response
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => static::CHANNEL_NAME]]);

        // assert channel creation
        $I->seeInDatabase('channels', ['channel_name' => static::CHANNEL_NAME]);

    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testChannelCreationWithDuplicateName(ApiTester $I)
    {
        $this->jwtToken = $I->loginUser(static::TEST_USER, static::TEST_USER_PASSWORD);

        // Create channel
        $I->sendPOST('/channel.create', ['channel_name' => static::CHANNEL_NAME]);

        // Create channel with same name and have it fail
        $I->sendPOST('/channel.create', ['channel_name' => static::CHANNEL_NAME]);

        // assert response
        $I->seeResponseCodeIs(400);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }

    /**
     * @param ApiTester $I
     * @throws Exception
     */
    public function testChannelCreationWithUserList(ApiTester $I)
    {

        $this->jwtToken = $I->loginUser(static::TEST_USER, static::TEST_USER_PASSWORD);

        $emails = "temp1@test.com,temp2@test.com,temp3@test.com";

        // Create channel
        $I->sendPOST('/channel.create', ['channel_name' => static::CHANNEL_NAME, 'users' => $emails]);

        // assert response
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['channel_name' => static::CHANNEL_NAME]]);

        $channelId = $I->grabDataFromResponseByJsonPath('$channel.id')[0] ;

        // assert channel/user creation, and the relation
        $I->seeInDatabase('channels', ['channel_name' => static::CHANNEL_NAME]);
        $I->seeInDatabase('users', ['email' => 'temp1@test.com']);
        $I->seeInDatabase('users', ['email' => 'temp2@test.com']);
        $I->seeInDatabase('users', ['email' => 'temp3@test.com']);
        $userId1 = $I->grabFromDatabase('users', 'id', ['email' => 'temp1@test.com']);
        $userId2 = $I->grabFromDatabase('users', 'id', ['email' => 'temp2@test.com']);
        $userId3 = $I->grabFromDatabase('users', 'id', ['email' => 'temp3@test.com']);
        $I->seeInDatabase('channel_users', ['channel_id' => $channelId, 'user_id' => $userId1]);
        $I->seeInDatabase('channel_users', ['channel_id' => $channelId, 'user_id' => $userId2]);
        $I->seeInDatabase('channel_users', ['channel_id' => $channelId, 'user_id' => $userId3]);
    }

    public function testGetChannelInfo(ApiTester $I)
    {

        // include a pre-created channel
        $I->loadFixture('channel/base_channel_created', false);

        // get the created channel id
        $channelId = $I->grabFromDatabase('channels', 'id', ['channel_name' => static::CHANNEL_NAME]);

        $this->jwtToken = $I->loginUser(static::TEST_USER, static::TEST_USER_PASSWORD);

        // Get Channel info
        $I->sendGET('/channel.info', ['channel_id' => $channelId]);

        // assert response
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['channel' => ['id' => $channelId]]);
    }

    /**
     * @param ApiTester $I
     */
    public function testGetChannelMembers(ApiTester $I)
    {
        // include a pre-created channel with members
        $I->loadFixture('channel/base_channel_created_with_members', false);

        // get the created channel id
        $channelId = $I->grabFromDatabase('channels', 'id', ['channel_name' => static::CHANNEL_NAME]);

        // login with the test user
        $this->jwtToken = $I->loginUser(static::TEST_USER, static::TEST_USER_PASSWORD);

        // Get Channel members
        $I->sendGET('/channel.members', ['channel_id' => $channelId]);

        // assert response
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['users' => ['display_name' => 'temp1@test.com']]);
        $I->seeResponseContainsJson(['users' => ['display_name' => 'temp2@test.com']]);
        $I->seeResponseContainsJson(['users' => ['display_name' => 'temp3@test.com']]);
        $I->seeResponseContainsJson(['users' => ['display_name' => 'Channel Test User']]);
    }

    /**
     * @param ApiTester $I
     */
    public function testGetChannelMembersWithoutBeingPartOfTheChannel(ApiTester $I)
    {
        // include a pre-created channel
        $I->loadFixture('channel/base_channel_created', false);

        // get the created channel id
        $channelId = $I->grabFromDatabase('channels', 'id', ['channel_name' => static::CHANNEL_NAME]);

        // include the not a member user
        $I->loadFixture('channel/not_a_member_user_created', false);

        // login with a user that is not part of the channel
        $this->jwtToken = $I->loginUser('notamember@airsendmail.com', 'password1');

        // try to get the channel members
        $I->sendGET('/channel.members', ['channel_id' => $channelId]);
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);

    }

    /**
     * @param ApiTester $I
     */
    public function testChannelInvite(ApiTester $I)
    {

        // include a pre-created channel
        $I->loadFixture('channel/base_channel_created', false);

        // get the created channel id
        $channelId = $I->grabFromDatabase('channels', 'id', ['channel_name' => static::CHANNEL_NAME]);

        // login with the test user
        $this->jwtToken = $I->loginUser(static::TEST_USER, static::TEST_USER_PASSWORD);

        // invite the users
        $payload = [
            'channel_id' => $channelId,
            'emails' => 'junk1@test.com,junk2@test.com,junk3@test.com',
        ];
        $I->sendPOST('/channel.invite', $payload);

        // assert response
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseContainsJson(['meta' => ['ok' => true]]);
        $I->seeResponseContainsJson(['users' => ['display_name' => 'junk1']]);
        $I->seeResponseContainsJson(['users' => ['display_name' => 'junk2']]);
        $I->seeResponseContainsJson(['users' => ['display_name' => 'junk3']]);

        // assert database
        $I->seeInDatabase('users', ['email' => 'junk1@test.com']);
        $I->seeInDatabase('users', ['email' => 'junk2@test.com']);
        $I->seeInDatabase('users', ['email' => 'junk3@test.com']);

        $userId1 = $I->grabFromDatabase('users', 'id', ['email' => 'junk1@test.com']);
        $userId2 = $I->grabFromDatabase('users', 'id', ['email' => 'junk2@test.com']);
        $userId3 = $I->grabFromDatabase('users', 'id', ['email' => 'junk3@test.com']);
        $I->seeInDatabase('channel_users', ['channel_id' => $channelId, 'user_id' => $userId1]);
        $I->seeInDatabase('channel_users', ['channel_id' => $channelId, 'user_id' => $userId2]);
        $I->seeInDatabase('channel_users', ['channel_id' => $channelId, 'user_id' => $userId3]);
    }

    /**
     * @param ApiTester $I
     */
    public function testChannelInviteWithoutBeingPartOfTheChannel(ApiTester $I)
    {
        // include a pre-created channel
        $I->loadFixture('channel/base_channel_created', false);

        // get the created channel id
        $channelId = $I->grabFromDatabase('channels', 'id', ['channel_name' => static::CHANNEL_NAME]);

        // include the not a member user
        $I->loadFixture('channel/not_a_member_user_created', false);

        // login with a user that is not part of the channel
        $this->jwtToken = $I->loginUser('notamember@airsendmail.com', 'password1');

        // try to invite the users
        $payload = [
            'channel_id' => $channelId,
            'emails' => 'junk1@test.com,junk2@test.com,junk3@test.com',
        ];
        $I->sendPOST('/channel.invite', $payload);

        // assert the error
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false]]);
    }

    /**
     * @param ApiTester $I
     */
    public function testUnauthenticatedChannelCreation(ApiTester $I)
    {

        // do not login...

        // Create channel
        $I->sendPOST('/channel.create', ['channel_name' => static::CHANNEL_NAME]);

        // assert response
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Unauthorized']]);

        // assert channel is not created
        $I->dontSeeInDatabase('channels', ['channel_name' => static::CHANNEL_NAME]);
    }

    /**
     * @param ApiTester $I
     */
    public function testUnauthenticatedGetChannelInfo(ApiTester $I)
    {
        // include a pre-created channel
        $I->loadFixture('channel/base_channel_created', false);

        // get the created channel id
        $channelId = $I->grabFromDatabase('channels', 'id', ['channel_name' => static::CHANNEL_NAME]);

        // do not login

        // Try to get Channel info
        $I->sendGET('/channel.info', ['channel_id' => $channelId]);

        // assert response
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Unauthorized']]);

    }

    /**
     * @param ApiTester $I
     */
    public function testUnauthenticatedChannelInvite(ApiTester $I)
    {
        // include a pre-created channel
        $I->loadFixture('channel/base_channel_created', false);

        // get the created channel id
        $channelId = $I->grabFromDatabase('channels', 'id', ['channel_name' => static::CHANNEL_NAME]);

        // do not login

        // try to invite the users
        $payload = [
            'channel_id' => $channelId,
            'emails' => 'junk1@test.com,junk2@test.com,junk3@test.com',
        ];
        $I->sendPOST('/channel.invite', $payload);

        // assert response
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Unauthorized']]);

        // assert database
        $I->dontSeeInDatabase('users', ['email' => 'junk1@test.com']);
        $I->dontSeeInDatabase('users', ['email' => 'junk2@test.com']);
        $I->dontSeeInDatabase('users', ['email' => 'junk3@test.com']);
    }

    /**
     * @param ApiTester $I
     */
    public function testUnauthenticatedChannelMembers(ApiTester $I)
    {
        // include a pre-created channel with members
        $I->loadFixture('channel/base_channel_created_with_members', false);

        // get the created channel id
        $channelId = $I->grabFromDatabase('channels', 'id', ['channel_name' => static::CHANNEL_NAME]);

        // do not login

        // Get Channel members
        $I->sendGET('/channel.members', ['channel_id' => $channelId]);

        // assert response
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['meta' => ['ok' => false, 'error' => 'Unauthorized']]);
    }

}