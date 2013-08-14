<?php

namespace KnpU\QADayBundle\Validator;

use Symfony\Component\Validator\Constraint;

/** @Annotation */
class UniqueEventDate extends Constraint
{
    public function validatedBy()
    {
        return 'unique_event_date';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}