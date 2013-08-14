<?php

namespace KnpU\QADayBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    public function indexAction(Request $request)
    {
        /**
        $hallo = new \Weaverryan\DangerZone\HalloThere();

        return new Response($hallo->sayHallo());

        return $this->render('QADayBundle:Default:index.html.twig');
        */

        /** @var $siteManager \KnpU\QADayBundle\Site\SiteManager */
        $siteManager = $this->container->get('site_manager');

        return $this->render('QADayBundle:Default:index.html.twig', array(
            'site' => $siteManager->getCurrentSite(),
        ));
    }

    public function parameterTestAction($_route)
    {
        return new Response(sprintf('We\'re using parameters! Matched "%s"!', $_route));
    }
}
