<?php

require_once 'vendor/autoload.php';

try {
    $configuration = parse_ini_file('configuration.ini');
    $password = $configuration['masterpass'];
    $reviewers_file = $configuration['reviewers_file'];
    //Accept image bot
    $token_accept = $configuration['token_accept'];
    $url_accept = $configuration['url_accept'];

    if (0 == strcmp($password, $_GET['password'])) {
        echo 'LINKING!!';
        $bot_accept = new \Telegram\Bot\Api($token_accept);
        $bot_accept->setWebhook(['url' => $url_accept]);

        //reset accepters chats
        $reviewers = array();
        file_put_contents($reviewers_file,serialize($reviewers));

        //Print Debug info
        Kint::dump($bot_accept->getWebhookInfo());
    } else {
        header('HTTP/1.0 403 Forbidden');
        die('You have no power in here. (B flat mayor)');
    }
} catch (Exception $e) {
    d($e);
}

?>
