<?php

/** @var ContainerInterface $container */

use CodeLathe\Application\ConfigRegistry;
use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Managers\Password\PasswordOperations;
use CodeLathe\Core\Objects\User;
use CodeLathe\Core\Utility\StringUtility;
use CodeLathe\Service\Database\DatabaseService;
use Psr\Container\ContainerInterface;


function getPassword($prompt = "Enter Password: ") {
    echo $prompt;

    system('stty -echo');

    $password = trim(fgets(STDIN));

    system('stty echo');

    echo PHP_EOL;

    return $password;
}

if(php_sapi_name() == "cli") {

    /** @var DataController $dataController */
    $dataController = $container->get(DataController::class);

    /** @var PasswordOperations $passwordOps */
    $passwordOps = $container->get(PasswordOperations::class);

    // first parse the command options
    $email = readline('User email: ');

    $user = $dataController->getUserByEmail($email);
    if ($user === null) {
        echo 'Invalid email...' . PHP_EOL;
        exit(1);
    }

    $generated = false;
    $password = getPassword('Enter password (empty to generate): ');
    if (empty($password)) {
        $generated = true;
        $password = StringUtility::generateRandomString(16);
    }
    if (strlen($password) < 8) {
        echo 'Please enter a valid password (at least 8 chars)' . PHP_EOL;
        exit(2);
    }

    echo PHP_EOL;
    echo "Changing password for user '$email'. ";
    if ($generated) {
        echo "New password (you'll only see it now, so save it securely): $password";
    }
    echo PHP_EOL;

    $confirm = readline('Confirm [n]: ');

    if (preg_match('/^[Yy]$|^[yY][eE][sS]$/', $confirm)) {

        // replace the password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $passwordOps->setNewPasswordForUser($user, $passwordHash);

        echo 'Password successfully changed!' . PHP_EOL;
        exit(0);

    }

    echo 'Aborted by the user...' . PHP_EOL;
    exit(3);


} else {
    echo "<html lang=\"en\"><body><h3>Cannot be executed via a Webserver</h3></body></html>";
}