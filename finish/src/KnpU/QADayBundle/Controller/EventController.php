<?php

namespace KnpU\QADayBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ExecutionContextInterface;
use KnpU\QADayBundle\Entity\Event;

class EventController extends Controller
{
    public function newAction(Request $request)
    {
        $form = $this->createFormBuilder(new Event(), array(
            'data_class' => 'KnpU\QADayBundle\Entity\Event',
        ))
            ->add('name', 'text')
            ->add('startDate', 'datetime')
            ->add('endDate', 'datetime')
            ->getForm()
        ;

        if ($request->isMethod('POST')) {
            $form->bind($request);

            if ($form->isValid()) {
                var_dump('Valid!', $form->getData());
            }
        }

        return $this->render('QADayBundle:Event:new.html.twig', array(
            'form' => $form->createView()
        ));
    }
}
