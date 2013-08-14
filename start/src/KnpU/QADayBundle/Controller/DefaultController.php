<?php

namespace KnpU\QADayBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('QADayBundle:Default:index.html.twig');
    }
}
