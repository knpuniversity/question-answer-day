How to (dynamically) remove a Form Field
========================================

From ThiagoKrug:

  Hi, I am reusing one form but I have one specific controller action that
  I need remove one field form, How can I do it without creating new form?
  Thanks!

Answer
------

.. note::

    Special thanks to our very-own `Roman`_ on this answer!

Cool question! There are actually multiple ways to achieve this task, ranging
from a very simple ``->remove()`` method call to setting up a form event
listener.

Let's review the two easiest and most common ways.

First, initialize a simple form type with two fields::

    // src/KnpU/QADayBundle/Form/Type/RemoveFormFieldType.php
    namespace KnpU\QADayBundle\Form\Type;

    use Symfony\Component\Form\AbstractType;
    use Symfony\Component\Form\FormBuilderInterface;

    class RemoveFormFieldType extends AbstractType
    {
        public function buildForm(FormBuilderInterface $builder, array $options)
        {
            $builder
                ->add('first', 'text')
                ->add('second', 'text')
            ;
        }

        public function getName()
        {
            return 'remove_form_field';
        }
    }

Next, let's build the two different controllers that will render this form
to show off the two solutions::

    // src/KnpU/QADayBundle/Controller/RemoveFormFieldController.php
    namespace KnpU\QADayBundle\Controller;

    use Symfony\Bundle\FrameworkBundle\Controller\Controller;

    use KnpU\QADayBundle\Form\Type\RemoveFormFieldType;

    class RemoveFormFieldController extends Controller
    {
        public function firstWayAction()
        {
            $form = $this->createForm(new RemoveFormFieldType());

            // form processing...

            return $this->render('QADayBundle:RemoveFormField:form.html.twig', array(
                'form' => $form->createView()
            ));
        }

        public function secondWayAction()
        {
            // same as firstWayAction() for now
        }
    }

Awesome! Now, let's solve this in 2 different ways.

Option 1: Using remove
----------------------

The most straightforward way to achieve this is by removing the form field
you want with the ``remove`` function::

    public function firstWayAction()
    {
        $form = $this->createForm(new RemoveFormFieldType());

        $form->remove('second');

        // form processing...

        return $this->render('QADayBundle:RemoveFormField:form.html.twig', array(
            'form' => $form->createView()
        ));
    }

And that's it! No changes to ``RemoveFormFieldType`` are required at all!
And the best part is that this is a perfectly valid solution, no need to over-think
it :).

Option 2: Using Form Options
----------------------------

But if you want a more advanced solution with a bit more flexibility, there's
another way!

First, let's tweak the form type a bit to rely on a custom option passed
on initialization::

    // src/KnpU/QADayBundle/Controller/RemoveFormFieldController.php
    use Symfony\Component\OptionsResolver\OptionsResolverInterface;
    // ...

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('first', 'text');
        
        if ($options['use_second']) {
            $builder->add('second', 'text');
        }
    }
    
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'use_second' => true
        ));
    }

Now, when you create the form, pass the option and you're ready to go!

.. code-block:: php

    public function secondWayAction()
    {
        // the "null" option is the form data - you might pass something here
        $form = $this->createForm(new RemoveFormFieldType(), null, array(
            'use_second' => false
        ));

        return $this->render('QADayBundle:RemoveFormField:form.html.twig', array(
            'form' => $form->createView()
        ));
    }

That's it! There is also an event dispatching/listening system in the Form
component, which allows you to dynamically add/remove/modify fields based
on anything (e.g. user-submitted data). For more information, see
`How to Dynamically Modify Forms Using Form Events`_.

Have fun!

.. _`How to Dynamically Modify Forms Using Form Events`: http://symfony.com/doc/current/cookbook/form/dynamic_form_modification.html
.. _`Roman`: https://twitter.com/Inoryy