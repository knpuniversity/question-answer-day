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


use KnpU\QADayBundle\Entity\Event;

/** @var $em \Doctrine\ORM\EntityManager */
$em = $container->get('doctrine')->getManager();
$em->createQuery('DELETE FROM QADayBundle:Event')->execute();

$event = new Event();
$event->setName('KnpU QA Day');
$event->setStartDate(new \DateTime('2013-03-27 12:00:00'));
$event->setEndDate(new \DateTime('2013-03-27 17:00:00'));

$em->persist($event);
$em->flush();