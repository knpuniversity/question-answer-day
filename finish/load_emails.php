<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

$loader = require_once __DIR__.'/app/bootstrap.php.cache';

require_once __DIR__.'/app/AppKernel.php';

$kernel = new AppKernel('prod', true);
$request = Request::createFromGlobals();

$kernel->boot();
$container = $kernel->getContainer();
$container->enterScope('request');
$container->set('request', $request);


/** @var $mailer \Swift_Mailer */
$mailer = $container->get('mailer');

$message = \Swift_Message::newInstance()
    ->setSubject('Testing Spooling!')
    ->setFrom('hello@knpuniversity.com')
    ->setTo('ryan@knplabs.com')
    ->setBody('Hallo emails!')
;
//var_dump($container->get('swiftmailer.transport.real'));die;

for ($i = 0; $i < 20; $i++) {
    $mailer->send($message);
}