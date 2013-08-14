<?php

namespace KnpU\QADayBundle\Validator;

use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Validator\Constraint;

class UniqueEventDateValidator extends ConstraintValidator
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function validate($object, Constraint $constraint)
    {
        $conflicts = $this->em
            ->getRepository('QADayBundle:Event')
            ->findOverlappingWithRange($object->getStartDate(), $object->getEndDate())
        ;

        if (count($conflicts) > 0) {
            $this->context->addViolationAt('startDate', 'There is already an event during this time!');
            $this->context->addViolation('There is already an event during this time!');
        }
    }
}