<?php

namespace KnpU\QADayBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ServiceController
{
    private $templating;

    private $router;

    public function __construct(EngineInterface $templating, UrlGeneratorInterface $router)
    {
        $this->templating = $templating;
        $this->router = $router;
    }

    public function indexAction(Request $request)
    {
        if ($request->isMethod('POST')) {
            // .. do some things

            $url = $this->router->generate('homepage');
            return new RedirectResponse($url);
        }

        return $this->templating->renderResponse(
            'QADayBundle::controllerTest.html.twig',
            array('type' => 'Container as a service!')
        );
    }
}
