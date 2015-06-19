Custom Validation, Callback and Constraints
===========================================

From `Rafael`_:

    Hi, I am coding one events calendar, It is adding events however how can
    I validate if the event I am placing does not conflict time with another
    one event? I was thinking about entity validation callback but should it
    be in entity? or repository? I don't want to lose symfony validation that
    display errors on the forms

Answer
------

This is a great question because it touches on a few interesting and related
concepts: custom validation, assigning errors, and the best practices around
all of this.

Let's follow along with your example. Suppose we have an ``Event`` entity
that looks like this (with some extras, like getter and setter methods)::

    // src/KnpU/QADayBundle/Entity/Event.php
    namespace KnpU\QADayBundle\Entity;

    use Doctrine\ORM\Mapping as ORM;

    /**
     * @ORM\Entity(repositoryClass="KnpU\QADayBundle\Entity\EventRepository")
     */
    class Event
    {
        /**
         * @ORM\Column(name="id", type="integer")
         * @ORM\Id
         * @ORM\GeneratedValue(strategy="AUTO")
         */
        private $id;

        /** @ORM\Column(name="name", type="string", length=255) */
        private $name;

        /** @ORM\Column(name="startDate", type="datetime") */
        private $startDate;

        /** @ORM\Column(name="endDate", type="datetime") */
        private $endDate;
        
        // ...
    }

I also have a really basic route, controller and form setup which allows
the user to create a new Event (check out the code download to see this).
Ok, let's get to work!

The Callback Constraint
~~~~~~~~~~~~~~~~~~~~~~~

The goal is to throw a validation error if the event will conflict with the
start and end times of some existing event. There are a few ways to add custom
validation, including the `Callback`_ constraint, which executes an arbitrary
method in your model/entity class and lets you apply any custom logic you
want::

    // src/KnpU/QADayBundle/Entity/Event.php
    // ...
    use Symfony\Component\Validator\Constraints as Assert;
    use Symfony\Component\Validator\ExecutionContextInterface;

    /**
     * @Assert\Callback(methods={"checkCustomValidation"})
     */
    class Event
    {
        // ...

        public function checkCustomValidation(ExecutionContextInterface $context)
        {
            $context->addViolationAt('name', 'Pick a cooler name!');
        }
    }

This is my favorite way to handle custom validation because it's so easy.
The problem is that the method lives in your entity. This means that you
don't have access to the entity manager or any other services. In this case,
there's no way to query to see if any other event has a conflicting date.

A bit Ugly, but Easy: Callback + constraints
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Normally, we add validation constraints to our model class (i.e. ``Event``).
However, as of Symfony 2.1, additional constraints can be added directly
to the form key using a ``constraints`` option. Like with annotations, you can
apply constraints to the whole object, or individual properties.

For simplicity, I've built my form in the controller instead of using a
`form type class`_. Let's re-use the ``Callback`` validator, but now tell
it to execute a method on my controller when called::

    // src/KnpU/QADayBundle/Controller/EventController.php

    use Symfony\Component\Validator\Constraints as Assert;
    use Symfony\Component\Validator\ExecutionContextInterface;
    use KnpU\QADayBundle\Entity\Event;
    // ...

    public function newAction(Request $request)
    {
        $form = $this->createFormBuilder(null, array(
            'data_class' => 'KnpU\QADayBundle\Entity\Event',
            'constraints' => array(
                new Assert\Callback(array($this, 'validateEventDates'))
            )
        ))
            ->add('name', 'text')
            ->add('startDate', 'datetime')
            ->add('endDate', 'datetime')
            ->getForm()
        ;

        // ...
    }

And for now, I've just put some dummy code into the ``validateEventDates``
function, which lives right inside this same class::
    
    // src/KnpU/QADayBundle/Entity/EventController.php
    public function validateEventDates(Event $event, ExecutionContextInterface $context)
    {
        $context->addViolationAt('startDate', 'There is already an event during this time!');
    }

Phew! Let's walk through this step-by-step:

1) We eventually want to validate our object based on multiple pieces of
data (the ``startDate`` and ``endDate``). So instead of applying a validator
to a single field, we apply it to the whole object. This means that when
the ``validateEventDates`` is called, the whole ``Event`` object is passed
to it.

2) To attach validation constraints directly to the form, we use the ``constraints``
key and create a new instance of the constraint. Whether you realized it
or not, all those ``Callback``, ``NotBlank``, etc keys that you use every
day for validation are each a real class.

3) When the ``Callback`` constraint is executed, it detects that we're no
longer inside the ``Event`` class. To help us out, it now passes our method
two arguments: the ``Event`` object and the execution context.

.. note::

    The ``Callback`` constraint - or any other constraint - can also be applied
    to just an individual field by adding a third argument to the ``add``
    function, which would be an array with a ``constraints`` key.

.. tip::

    If your form lives in a `form type class`_, simply add the ``constraints``
    key to the ``setDefaulOptions`` method.

This solution is a bit ugly because it lives in our Controller, so we can't
re-use it or unit test it. We'll improve that in a second, but let's get
it working first!

Applying the Validation Logic
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Now that the callback method lives in the controller, we can easily access
the entity manager (or any other service) and run the queries we need to.
And since we are going to be executing some queries, the best place for that
logic is in the ``EventRepository`` class::

    // src/KnpU/QADayBundle/Entity/EventRepository.php
    namespace KnpU\QADayBundle\Entity;

    use Doctrine\ORM\EntityRepository;

    class EventRepository extends EntityRepository
    {
        public function findOverlappingWithRange(\DateTime $startDate, \DateTime $endDate)
        {
            $qb = $this->createQueryBuilder('e');

            return $qb->andWhere('e.startDate < :endDate AND e.endDate > :startDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->getQuery()
                ->execute()
            ;
        }
    }

Great! Now use this function in the callback method in the controller::

    // src/KnpU/QADayBundle/Controller/EventController.php
    public function validateEventDates(Event $event, ExecutionContextInterface $context)
    {
        $conflicts = $this->getDoctrine()
            ->getRepository('QADayBundle:Event')
            ->findOverlappingWithRange($event->getStartDate(), $event->getEndDate())
        ;

        if (count($conflicts) > 0) {
            $context->addViolationAt(
                'startDate',
                'There is already an event during this time!'
            );
        }
    }

.. tip::

    If this method lives in your form type class, then you don't have the
    entity manager! One option is to pass it in as an option when creating
    your form::
    
        $form = $this->createForm(new EventType, null, array(
            'em' => $this->getDoctrine()->getManager()
        ))

    The ``em`` option is then available in the ``buildForm`` method of the
    form type class::
    
        public function buildForm(FormBuilderInterface $builder, array $options)
        {
            $em = $options['em'];
        }
    
    For this to work, make sure to add ``em`` to the "defaults" in your form
    type's ``setDefaultOptions`` method.

If you try it, it works! It's a bit dirty, but at least our query logic lives
in ``EventRepository``. If you were also handling "edits", you'd also need
to make sure that the result isn't the exact object being saved. But I'll
leave that to you!

Creating a Proper Custom Validation Constraint
----------------------------------------------

There's nothing wrong with what we have so far, but for the sake of reusability,
clean code and unit testing, it can be much better.

The ultimate solution to custom validation is to create your own constraint.
Fortunately, we've already done most of the work. Start by creating a new
``UniqueEventDate`` class::

    // src/KnpU/QADayBundle/Validator/UniqueEventDate.php
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

Yep, this class is so simple it's silly. Each custom validation constraint
is actually two classes: one "Constraint" (seen here) that holds some options
and another "Constraint Validator" (shown next) which does all the work. In
fact, you can find these for the built-in constraints, for example ``NotBlank``
and ``NotBlankValidator``.

There are 3 interesting parts to this class:

1) The ``@Annotation`` will eventually allow us to reference this constraints
in the Event class via, well, annotations.

2) The ``validatedBy`` tells Symfony about the "Constraint Validator" that
will actually do the heavy lifting. The ``unique_event_date`` string shouldn't
make sense yet - but it'll be more obvious in a minute.

3) The ``getTargets`` method defines whether this constraint can be applied
to an entire class, a property, or both. Again, since we need multiple values
on ``Event`` in order to make our validation decision, we will apply the
constraint to the entire class.

.. tip::

    This example doesn't use any constraint options. If you do want to see what
    it looks like to have a constraint that has configurable options, see
    the core `Email`_ and `EmailValidator`_ classes.

Next, create the "Constraint Validator" class::

    // src/KnpU/QADayBundle/Validator/UniqueEventDateValidator.php
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
            die('hold on, we\'ll fill finish this in a second...');
        }
    }

In a second, we'll fill this class in and have it do all the validation work.
But first, register it as a service and tag it with a special `validator.constraint_validator`_
tag:

.. code-block:: yaml

    # src/KnpU/QADayBundle/Resources/config/services.yml
    services:
        unique_event_date_validator:
            class: KnpU\QADayBundle\Validator\UniqueEventDateValidator
            arguments:
                - "@doctrine.orm.entity_manager"
            tags:
                -
                    name: validator.constraint_validator
                    alias: unique_event_date

.. note::

    Make sure this ``services.yml`` file is being imported, either by using
    an `imports key`_ in ``app/config/config.yml`` or via a
    `Dependency Injection Extension`_ class (see `Episode 3`_ for more on this).

Notice that the ``alias`` we use with the tag corresponds with the value
that the Constraint class returns in ``validateBy``. This is how Symfony
knows that the ``UniqueEventDateValidator`` is the real muscle behind the
``UniqueEventDate`` constraint.

Ok! Before we fill in the logic in the ``validate`` method, let's try this
out! The new constraint isn't magically activated - we activate it like any
other constraint, with annotations (or YAML, if you prefer)::

    // src/KnpU/QADayBundle/Entity/Event.php
    // ...

    use KnpU\QADayBundle\Validator\UniqueEventDate;

    /**
     * @ORM\Entity(repositoryClass="KnpU\QADayBundle\Entity\EventRepository")
     * @UniqueEventDate()
     */
    class Event
    {
        // ...
    }

When you submit the form, the ``UniqueEventDate`` constraint is triggered,
and ultimately the ``UniqueEventDateValidator::validate`` method is called.
In other words, you'll see our ``die`` statement print.

Ok, let's finish this! Copy the logic from the controller ``validateEventDates``
method and remove it and the ``constraints`` option while you're there.
Paste it into ``UniqueEventDateValidator::validate`` and adjust it accordingly::

    // src/KnpU/QADayBundle/Validator/UniqueEventDateValidator.php
    public function validate($object, Constraint $constraint)
    {
        $conflicts = $this->em
            ->getRepository('QADayBundle:Event')
            ->findOverlappingWithRange($object->getStartDate(), $object->getEndDate())
        ;

        if (count($conflicts) > 0) {
            $this->context->addViolationAt('startDate', 'There is already an event during this time!');
        }
    }

Let's walk through the differences:

1) Since we've injected Doctrine's Entity Manager, we can access it and get
the ``EventRepository`` through ``$this->em``.

2) Since we applied the ``UniqueEventDate`` constraint to the ``Event`` class,
the entire ``Event`` object is passed as the first argument to this method
(i.e. ``$object``).

3) The ``ExecutionContext`` is stored automatically on the ``$this->context``
property.

That's it! When you re-submit the form, the ``UniqueEventDate`` constraint
on ``Event`` activates this method, which does all the work.

Through all of this, one nice thing is that we were always in complete control
of which field our error was attached to. I chose to attach the error to
the ``startDate`` field, but you can use whatever makes sense to you. If
you use the ``addViolation`` method instead, the error will be attached to
the whole form and displayed at the top::

    $this->context->addViolation('There is already an event during this time!');

Ok, start validating!

.. _`Rafael`: https://twitter.com/dextervip
.. _`Callback`: http://symfony.com/doc/current/reference/constraints/Callback.html
.. _`form type class`: http://symfony.com/doc/current/book/forms.html#creating-form-classes
.. _`Expression Builder`: http://docs.doctrine-project.org/en/2.0.x/reference/query-builder.html#the-expr-class
.. _`Email`: https://github.com/symfony/symfony/blob/2.2/src/Symfony/Component/Validator/Constraints/Email.php
.. _`EmailValidator`: https://github.com/symfony/symfony/blob/2.2/src/Symfony/Component/Validator/Constraints/EmailValidator.php
.. _`validator.constraint_validator`: http://symfony.com/doc/current/reference/dic_tags.html#validator-constraint-validator
.. _`imports key`: http://symfony.com/doc/current/book/service_container.html#importing-configuration-with-imports
.. _`Dependency Injection Extension`: http://symfony.com/doc/current/book/service_container.html#importing-configuration-via-container-extensions
.. _`Episode 3`: http://knpuniversity.com/screencast/starting-in-symfony2-episode-3-2-1
