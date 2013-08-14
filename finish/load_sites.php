<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

$loader = require_once __DIR__.'/app/bootstrap.php.cache';

require_once __DIR__.'/app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$request = Request::createFromGlobals();

$kernel->boot();
$container = $kernel->getContainer();
$container->enterScope('request');
$container->set('request', $request);


use KnpU\QADayBundle\Entity\Site;

/** @var $em \Doctrine\ORM\EntityManager */
$em = $container->get('doctrine')->getManager();
$em->createQuery('DELETE FROM QADayBundle:Site')->execute();

$site1 = new Site();
$site1->setSubdomain('kittens');
$site1->setName('Cute Kittens');
$site1->setDescription('I\'m peerrrrfect!');

$site2 = new Site();
$site2->setSubdomain('alpacas');
$site2->setName('Funny Alpacas');
$site2->setDescription('Alpaca my bags!');

$em->persist($site1);
$em->persist($site2);
$em->flush();