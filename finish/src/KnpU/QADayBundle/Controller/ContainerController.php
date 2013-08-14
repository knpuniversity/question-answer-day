<?php

namespace KnpU\QADayBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

class ContainerController implements ContainerAwareInterface
{
    private $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function indexAction(Request $request)
    {
        if ($request->isMethod('POST')) {
            // .. do some things

            $url = $this->container->get('router')->generate('homepage');
            return new RedirectResponse($url);
        }

        return $this->container->get('templating')->renderResponse(
            'QADayBundle::controllerTest.html.twig',
            array('type' => 'Injecting the container!')
        );
    }

    /**
     * @return \Symfony\Component\Routing\Generator\UrlGeneratorInterface
     */
    private function getRouter()
    {
        return $this->container->get('router');
    }
}
