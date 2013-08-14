Conditionally Requiring a Form Field in Symfony2
================================================

From `David`_

    Is there a sane way with the form layer and a custom form type to determine
    if a field is required based on the actual content that is bound to it?
    I hacked up this gist which i hope shows the idea:
    
    https://gist.github.com/dbu/5142035

Answer
------

If you don't know David, he's a fantastic developer who works at Liip and
spends a lot of time working with the `Symfony CMF`_ project. So, when I
saw a question from him, I knew it would be tough! Depending on exactly
what you're trying to do, this may or may not have a great solution, but
we'll learn a lot about building form fields, events and form configuration
along the way.

This question is all about being able to dynamically modify a form field
after its already been built. Typically, this is done by using a `form event`_
and looks something like this::

    class AddressType extends AbstractType
    {
        public function buildForm(FormBuilderInterface $builder, array $options)
        {
            $builder
                // ...
                ->add('country', 'count', array(...))
            ;

            $builder->addEventListener(
                FormEvents::PRE_BIND,
                function(FormEvent $event) use($factory){
                    $data = $event->getData();
                    $form = $event->getForm();

                    $country = $data['country'];
                    $form->add('state', 'choice', array(
                        'choices' => array() // build state choices from country
                    ))
                }
            );
        }
    }

.. note::

    This example is a little incomplete. See `How to Dynamically Modify Forms Using Form Events`_.

In this case, the ``state`` field isn't built initially: it waits until the
form data is set and then is built based off of the value of the ``country``
field.

David's example is a little bit more difficult. In the above example, your
"form" is modifying a child field. However in David's example, a field is
modifying itself.

To see the problem - and talk about possible and impossible solutions - let's
start with a custom form type that extends the built-in ``file`` type::

    // src/KnpU/QADayBundle/Form/Type/ImageType.php
    namespace KnpU\QADayBundle\Form\Type;

    use Symfony\Component\Form\AbstractType;
    use Symfony\Component\OptionsResolver\OptionsResolverInterface;

    class ImageType extends AbstractType
    {
        public function getName()
        {
            return 'my_image';
        }

        public function getParent()
        {
            return 'file';
        }

        public function setDefaultOptions(OptionsResolverInterface $resolver)
        {
            $resolver->setDefaults(array(
                'required' => true,
            ));
        }
    }

This isn't very interesting yet, and defines a new field type that looks
and acts just like the normal ``file`` type. We've made it always default
to ``required``, which is actually the default behavior.

Making a field conditionally-required
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Right now, the field is *always* required. Our goal is to make it only required
if the base object that it's attached to is "unsaved". If you imagine we're creating
an ``Event`` that has an image, then the user should be required to upload
the image when creating the event, but then not required when editing later.

But first, what exactly does the ``required`` option do? In fact, it has
nothing at all to do with server-side validation, which is handled by an
entirely different mechanism (and that would also need to be adjusted to
meet our end-goal). The ``required`` option is used in exactly two places
by default:

1) It controls the ``required`` `form view variable`_, which determines whether
or not the HTML5 ``required`` attribute should be used on the field.

2) It's used in the default implementation of the `empty_data`_ option. When
a form or field has no data, this option is used to give it data. Typically
the empty data is either an empty string or an empty ``array()``. But if
your field or form has a `data_class`_ option, then something different happens.
If ``required`` is true, the "empty data" is a new instance of the object
specified in ``data_class``. If it's ``false``, then your empty data is simply
null.

In this example, we don't really care about the second usage (though it's
really interesting!): we simply want to prevent the ``required`` attribute
from printing.

The easiest way to do this is by overriding the ``buildView`` method in your
custom field::

    // src/KnpU/QADayBundle/Form/Type/ImageType.php
    use Symfony\Component\Form\FormView;
    use Symfony\Component\Form\FormInterface;
    // ...

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($form->getParent()->getData()->getId()) {
            // this is not new, so make it not required
            $view->vars['required'] = false;
        }
    }

But before you run and put this in your project, let's talk about several
big assumptions that this makes:

1) This assumes that your field has been added to a form with a ``data_class``
option. The ``$form->getParent()->getData()`` would then return that object.

2) This assumes that this parent object has a ``getId`` function, and that
calling it is the correct way of checking whether or not the field should
be required.

These may vary in your project, and you might even choose to make them configurable
in some way.

A solution that doesn't work: Event Listeners
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Let's also talk about one solution that does *not* work in this case: form
event listeners. Typically, an event listener is used when you want to modify
a form field based on some data - often the underlying data in the form itself.
This actually sounds like *exactly* what we want, so let's try a simple example
(which is basically taken from the gist mentioned in David's question)::

    use Symfony\Component\Form\FormEvents;
    use Symfony\Component\Form\FormEvent;
    // ...

    class ImageType extends AbstractType
    {
        public function buildForm(FormBuilderInterface $builder, array $options)
        {
            $builder->addEventListener(
                FormEvents::PRE_SET_DATA,
                array($this, 'determineRequired')
            );
        }

        public function determineRequired(FormEvent $event)
        {
            $imageForm = $event->getForm();

            if (!$imageForm->getParent()->getData()->getId()) {
                /** @var $formConfig FormBuilderInterface */
                $formConfig = $imageForm->getConfig();

                $formConfig->setRequired(true);
            }
        }
    }

Sadly, this does *not* actually work. As soon as you call ``setRequired``,
you'll see the following error:

  FormConfigBuilder methods cannot be accessed anymore once the builder is
  turned into a FormConfigInterface instance.

That's a bit technical, and relates to how we configure "form builders" and
eventually those are used to create the true "Form" object. In this case,
it's just too late to do this. The key difference between this and a normal
"form events" example is that this field is trying to modify *itself*, whereas
usually an entire form will use an event to modify a child field. It turns
out that in practice, this seems to make a huge difference.

But this does at least show a few interesting things about the low-level
life of a form. First, many of the options that you pass when building a form
field are ultimately available on the final Form object. Often, these are
actually stored on a `FormConfigInterface`_ object, accessible via ``$form->getConfig()``::

    $config = $form->getConfig();

But since this solution doesn't actually work, your best method - unless
there's a solution hiding somewhere - is to find out what behavior the ``required``
option causes, and change that behavior directly. Earlier, we did exactly
that by modifying the ``required`` form view variable which controls the HTML5
``required`` attribute.

Happy forming!

.. _`David`: https://twitter.com/dbu
.. _`Symfony CMF`: http://cmf.symfony.com/
.. _`form event`: http://symfony.com/doc/current/cookbook/form/dynamic_form_modification.html
.. _`How to Dynamically Modify Forms Using Form Events`: http://symfony.com/doc/current/cookbook/form/dynamic_form_modification.html
.. _`form view variable`: http://symfony.com/doc/2.1/reference/forms/twig_reference.html#twig-reference-form-variables
.. _`empty_data`: https://github.com/symfony/symfony-docs/pull/2415/files#diff-cd77711e4dce85be889ebba14db0ba41
.. _`data_class`: http://symfony.com/doc/current/book/forms.html#book-forms-data-class
.. _`FormConfigInterface`: https://github.com/symfony/symfony/blob/master/src/Symfony/Component/Form/FormConfigInterface.php