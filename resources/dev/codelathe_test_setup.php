<?php declare(strict_types=1);
/*******************************************************************************
 * Copyright(c) 2019 CodeLathe. All rights Reserved.
 *******************************************************************************/

use CodeLathe\Core\Managers\Channel\ChannelOperations;
use CodeLathe\Core\Managers\User\UserOperations;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Objects\ChannelUser;
use CodeLathe\Service\ServiceRegistryInterface;
use Psr\Container\ContainerInterface;

if(php_sapi_name() != "cli") {
    return;
}

/** @var ContainerInterface $container */

// instantiate the root pdo object to check if the users table is empty
$registry = $container->get(ServiceRegistryInterface::class);
$rootConn = $registry->get('/db/cloud_db_root/conn');
$rootUser = $registry->get('/db/cloud_db_root/user');
$rootPassword = $registry->get('/db/cloud_db_root/password');
$dbh = new PDO($rootConn, $rootUser, $rootPassword);

if ($dbh->query('SELECT 1 FROM asclouddb.users WHERE user_role NOT IN (5,90,100) LIMIT 1')->rowCount() > 0) {
    echo 'Database is not fresh. Exiting...' . PHP_EOL;
    exit(0);
}

echo "Setting up Test Accounts for CodeLathe Dev" . PHP_EOL;

$setupdata =
[
    'madhan@codelathe.com' => [ 'madhan@codelathe.com', 'Madhan Kanagavel', ['CodeLathe All', 'AirSend Team']],
    'anis@codelathe.com' => ['anis@codelathe.com', 'Anis Abdul', ['CodeLathe All', 'AirSend Team', 'Mobile Team']],
    'sathya@codelathe.com' => ['sathya@codelathe.com', 'Sathya Ramanathan', ['CodeLathe All', 'AirSend Team']],
    'attila@codelathe.com' => ['attila@codelathe.com', 'Boer Attila', ['CodeLathe All', 'AirSend Team']],
    'paulo.silva@codelathe.com' => ['paulo.silva@codelathe.com', 'Paulo Silva', ['CodeLathe All', 'AirSend Team']],
    'jeferson.almeida@codelathe.com' => ['jeferson.almeida@codelathe.com', 'Jeferson Almeida', ['CodeLathe All', 'AirSend Team']],
    'rkrish@codelathe.com' => ['rkrish@codelathe.com', 'Rada Krish', ['CodeLathe All', 'AirSend Team']],
    'corentin@codelathe.com' => ['corentin@codelathe.com', 'Corentin Watts', ['CodeLathe All', 'AirSend Team', 'Mobile Team']],
    'ernesto@codelathe.com' => ['ernesto@codelathe.com', 'Ernesto Rivera', ['CodeLathe All', 'AirSend Team', 'Mobile Team']],
    'chris.michael@codelathe.com' => ['chris.michael@codelathe.com', 'Chris Michael', ['CodeLathe All', 'AirSend Team']],
    'aruna@codelathe.com' => ['aruna@codelathe.com', 'Aruna', ['CodeLathe All', 'AirSend Team']],
    'nagapriya@codelathe.com' => ['nagapriya@codelathe.com', 'Nagapriya', ['CodeLathe All', 'AirSend Team']],
    'vramasamy@codelathe.com' => ['vramasamy@codelathe.com', 'Venkat Ramasamy', ['CodeLathe All']],
    'manoj@codelathe.com' => ['manoj@codelathe.com', 'Manoj Mohandas', ['CodeLathe All']],
    'sreeja@codelathe.com' => ['sreeja@codelathe.com', 'Sreeja Mohandas', ['CodeLathe All', 'AirSend Team']],
    'hameetha@codelathe.com' => ['hameetha@codelathe.com', 'Hameetha Ahamed', ['CodeLathe All']],
    'daniel@codelathe.com' => ['daniel@codelathe.com', 'Daniel Alarcon', ['CodeLathe All']],
    'soumya@codelathe.com' => ['soumya@codelathe.com', 'Soumya', ['CodeLathe All']],
    'tomasz@codelathe.com' => ['tomasz@codelathe.com', 'Tomasz Formański', ['CodeLathe All']],
    'navya@codelathe.com' => ['navya@codelathe.com', 'Navya Manoj', ['CodeLathe All']],
    'nandakumar@codelathe.com' => ['nandakumar@codelathe.com', 'Nandakumar Chitrasuresh', ['CodeLathe All']],
    'deepti@codelathe.com' => ['deepti@codelathe.com', 'Deepti Dixit', ['CodeLathe All']],
    'jack@codelathe.com' => ['jack@codelathe.com', 'Jack ', ['CodeLathe All']],
    'sanu@codelathe.com' => ['sanu@codelathe.com', 'Sanu', ['CodeLathe All']],
    'omar.sefian@codelathe.com' => ['omar.sefian@codelathe.com', 'Omar Sefian', ['CodeLathe All']],
    'ashish.gupta@codelathe.com' => ['ashish.gupta@codelathe.com', 'Ashish Gupta', ['CodeLathe All']],
    'nejin.v@codelathe.com' => ['nejin.v@codelathe.com', 'Nejin', ['CodeLathe All']],
    'wail.bouziane@codelathe.com' => ['wail.bouziane@codelathe.com', 'Wail', ['CodeLathe All']],
    'hari.mohan@codelathe.com' => ['hari.mohan@codelathe.com', 'Hari', ['CodeLathe All']],
    'nikhil.chourasia@codelathe.com' => ['nikhil.chourasia@codelathe.com', 'Nikhil', ['CodeLathe All']],
    'kathy.tibon@codelathe.com' => ['kathy.tibon@codelathe.com', 'Kathy Tibon', ['CodeLathe All']],
    'piotr.slupski@codelathe.com' => ['piotr.slupski@codelathe.com', 'Piotr', ['CodeLathe All']],
    'niharika.sah@codelathe.com' => ['niharika.sah@codelathe.com', 'Niharika', ['CodeLathe All']],
    'maya.daivajna@codelathe.com' => ['maya.daivajna@codelathe.com', 'Maya', ['CodeLathe All']],
    'czar.pino@codelathe.com' => ['czar.pino@codelathe.com', 'Czar', ['CodeLathe All']],
    'febin.chacko@codelathe.com' => ['febin.chacko@codelathe.com', 'Febin', ['CodeLathe All']],
    'stephin.babu@codelathe.com' => ['stephin.babu@codelathe.com', 'Stephin', ['CodeLathe All']],
    'kevin.reyes@codelathe.com' => ['kevin.reyes@codelathe.com', 'Kevin', ['CodeLathe All']],
    'henry.arnold@codelathe.com' => ['henry.arnold@codelathe.com', 'Henry', ['CodeLathe All']],
    'anand.prabhu@codelathe.com' => ['anand.prabhu@codelathe.com', 'Anand', ['CodeLathe All']],
    'doug.tough@codelathe.com' => ['doug.tough@codelathe.com', 'Doug', ['CodeLathe All']],
    'hazzellel.calimot@codelathe.com' => ['hazzellel.calimot@codelathe.com', 'Hazzellel', ['CodeLathe All']],
    'morgan.mckellar@codelathe.com' => ['morgan.mckellar@codelathe.com', 'Morgan', ['CodeLathe All']],
    'stefan.strauss@codelathe.com' => ['stefan.strauss@codelathe.com', 'Stefan', ['CodeLathe All']],
    'somitha.syriac@codelathe.com' => ['somitha.syriac@codelathe.com', 'Somitha', ['CodeLathe All']],
    'edielaine.suezo@codelathe.com' => ['edielaine.suezo@codelathe.com', 'Elaine', ['CodeLathe All']],
    'facundo.gimenez@codelathe.com' => ['facundo.gimenez@codelathe.com', 'Facundo', ['CodeLathe All']],
    'jagadeesh.jayachandran@codelathe.com'  => ['jagadeesh.jayachandran@codelathe.com', 'Jagadeesh', ['CodeLathe All']],
    'esther.cheng@codelathe.com'  => ['esther.cheng@codelathe.com', 'Esther', ['CodeLathe All']],
    'mela.lozano@codelathe.com'  => ['mela.lozano@codelathe.com', 'Mela', ['CodeLathe All']],
    'krzysztof.lukaszewicz@codelathe.com'  => ['krzysztof.lukaszewicz@codelathe.com', 'krzysztof', ['CodeLathe All']],
];

$dc = new DataController($container);

/** @var ChannelOperations $co */
$co = $container->get(ChannelOperations::class);

/** @var UserOperations $uo */
$uo = $container->get(UserOperations::class);

$channelMap = [];
foreach ($setupdata as $key => $data)
{
    $email = $key;
    $password = $data[0];
    $displayname = $data[1];
    $channels = $data[2];
    $phone = null;

    //... First create user
    $user = $uo->createUser($email, $phone, $password, $displayname,
        User::ACCOUNT_STATUS_ACTIVE, User::USER_ROLE_EDITOR, User::APPROVAL_STATUS_APPROVED, false, null, false);
    $user->setIsEmailVerified(true);
    $dc->updateUser($user);

    if (!isset($user))
    {
        echo "Failed to create User: ".$email;
    }
    echo "Created User: ".$email."\r\n";
    $userId = $user->getId();

    // .. Verify On New Channel Creation
    foreach ($channels as $channelname) {
        $channelname = trim($channelname);
        if (!isset($channelMap[$channelname])) {
            $channel = $co->createChannel($user, $channelname);
            $channelMap[$channelname] = [$channel->getId(), $userId];
            echo "Created Channel: ".$channelname." by " . $email. " : "."Id: ".$channel->getId()."\r\n";
        } else {
            $co->addUserToChannel($channelMap[$channelname][0], $email, $channelMap[$channelname][1], ChannelUser::CHANNEL_USER_ROLE_COLLABORATOR);
            echo "Adding User to Channel: ".$email." -> ".$channelname."\r\n";
        }
    }
}
echo "Done"."\r\n";
