Symfony2: Make my Controllers Services?
=======================================

From Christian:

    Hi,

    I'd like to know what you think about the practice of building
    "controllers as a service" as suggested here:

    http://pooteeweet.org/blog/1947
    
    https://github.com/symfony/symfony-docs/issues/457

    Thanks! And keep up the great work!

Answer
------

This is a big religious topic in the Symfony2 community, and if you scan
the comments in the links above, you'll see why. In fact, it's not something
I usually talk about: it can be a hornet's nest :). So here we go!

:ref:`In a moment<symfony2-controllers-services>`, we're going to walk through
an example and compare the approaches. But first, I'll say that I **don't
register my controllers as services**, and the reasons behind this are simple:

1) Registering a controller as a service is more work. That's not the worst
things ever, but since it takes longer, the rewards need to outweigh this.

2) All of your logic should be pushed out into your service layer anyways.
This is the age-old `skinny controllers`_ best-practice.

3) And now that your controllers are skinny, there's no need to unit test
them. Instead unit test the services being used by your controllers.

4) Services used by your controller are loaded lazily. This is not the
case if you've registered your controllers as a service and inject only what
you need. But in theory, as long as you keep your controllers :ref:`focused<symfony2-controllers-focused>`,
then what you're injecting will need to be used for any action anyways.

With that viewpoint, the slight increase in setup time probably doesn't make
registering your controllers as services worth it. And when we're teaching
beginners, it would be yet another concept to need to know early-on.

But as you dive in deeper, the topic gets more complex and the advantages
more fascinating, especially for seasoned developers that can register a
service very quickly.

.. _symfony2-controllers-services:

A Case for Services
-------------------

The advantages to registering your controllers as services are more subtle
but compelling!

Let's build two controllers so we can compare each in detail.

Injecting the Container - without the Base Controller
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The routing for the first looks normal:

.. code-block:: yaml

    controller_container:
        path: /controller/container
        defaults:
            _controller: QADayBundle:Container:index

Next, let's look at the controller class itself::

    // src/KnpU/QADayBundle/Controller/ContainerController.php
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
    }

In your Symfony2 projects, you're probably used to inheriting
`Symfony2's base Controller class`_. This gives you shortcut methods and
makes sure that Symfony's container is set on a ``container`` property. To
see what's really happening, I've chosen *not* to extend this class. Instead,
by implementing ``ContainerAwareInterface``, we can still make sure that
Symfony calls ``setContainer`` and passes it to us. After that, we grab services
directly from the container and use them. This is all exactly what happens
behind-the-scenes in your controllers when you extend Symfony's base Controller
class.

Creating a Controller as a Service
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Next, let's create that same controller, except register it as a service
and only inject what we need. First, the routing:

.. code-block:: yaml

    controller_service:
        path: /controller/service
        defaults:
            _controller: qa_day.controller.service:indexAction

Notice the ``_controller`` key looks different. We haven't yet, but in a
moment we'll create a new service called ``qa_day.controller.service``. Notice
that we **do** include the ``Action`` suffix with the method name: when you
refer to a controller as a service, none of the normal conventions are assumed
(i.e. ``index`` => ``indexAction``).

Next, the actual controller class::

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

The class is perfectly straightforward: we need the ``templating`` and ``router``
services, so we inject them. For extra-credit, I've type-hinted the interface
for each of these. Now, instead of referencing the ``router`` through the
``container``, we can just reference it directly. You can't see it here,
but my IDE is also giving me auto-completion on the ``templating`` and ``router``
objects - that's one major advantage.

.. tip::

    Knowing which interface to use for a specific service is not always easy.
    For example, how did I know to use ``EngineInterface`` for the ``templating``
    service? If you're not sure what to use, just look for the service in
    ``container:debug`` and use the actual class name - not interface - that
    is used for the service. To see if there's an interface, open that class
    up and check for it. This isn't a science, but it's a good path to learn
    more about the interfaces that are actually behind things.

Finally, we have to do the *extra* step: defining the controller as a service:

.. code-block:: yaml

    # src/KnpU/QADayBundle/Resources/config/services.yml
    services:
        qa_day.controller.service:
            class: KnpU\QADayBundle\Controller\ServiceController
            arguments: ["@templating", "@router"]

This is a totally normal and underwhelming service, but it completes the
equation. The ``qa_day.controller.service:indexAction`` value used for the ``_controller``
key of our route tells Symfony to grab this service and then execute ``indexAction``.

.. note::

    Make sure this ``services.yml`` file is being imported, either by using
    an `imports key`_ in ``app/config/config.yml`` or via a
    `Dependency Injection Extension`_ class (see `Episode 3`_ for more on this).

Comparing the two approaches: A case for Services
-------------------------------------------------

Since we've already talked about why you might *not* register a controller
as as service, let's explore the advantages of using services. Many of these
are summarized from `Lukas' blog`_ and comments:

1) Since you're not injecting the whole container, this is an opportunity
to **document what your controller does and doesn't do**. When the controller
is a service, it's obvious at a glance that it generates URLs and renders
templates. We also know that it doesn't talk to the database, send emails,
or do anything else.

To make this even cooler, `Lukas points out`_ that if you use the `JMSDebugginBundle`_,
then you can use the profiler tool to get a clear vision of what parts of
your code - including dependencies - make use of a particular service [`screenshot`_].
That's quite powerful.

2) Injecting specific services gives you **auto-completion and clarity on
exactly what types of objects you have**. When you reference the services
through the container, you don't *really* know what type of object you'll
get out. I commonly work around this by creating a private getter function
which tells my IDE what to expect::
    
    /**
     * @return \Symfony\Component\Routing\Generator\UrlGeneratorInterface
     */
    private function getRouter()
    {
        return $this->container->get('router');
    }

Still, if we gain some time by not registering our controller as a service,
it's fair to say that we lose some time doing things like this. It's also
technically possible that someone in our code changes the ``router`` to return
something that does **not** implement ``UrlGeneratorInterface``. In the service
controller, PHP would throw a very clear error if this ever happened.
In the container controller, the error would be less clear.

.. _symfony2-controllers-focused:

3) How much should your controller do? When you inject the entire container,
you could potentially have controllers that control many pages that do many
different things. As `Kris points out`_, this is much harder if your controller
is a service, since eventually you'll be injecting 100 different dependencies.
This is a natural way to **make sure controllers stay focused**.

To Service or not Service?
--------------------------

Since not taking a side is lame, I'll pick my winner. But the true answer
is that the best approach depends on who you are and your project.

For most people, **don't register your controllers as services**. It's simpler,
faster to develop, and avoids non-lazily-loaded service concerns.

So who should register controllers as services? If your team is very comfortable
with service-oriented-architecture and your project is quite large, where
it's a challenge to keep track of what pieces affect other pieces, then it
starts to make more sense. Like with a lot of things in technology, by choosing
this path you're asking to handle more complexity but understand that the
advantageous for you outweigh that concern.

Phew, ok, have fun! 

.. _`Lukas' blog`: http://pooteeweet.org/blog/1947
.. _`Symfony2's base Controller class`: https://github.com/symfony/symfony/blob/2.2/src/Symfony/Bundle/FrameworkBundle/Controller/Controller.php
.. _`imports key`: http://symfony.com/doc/current/book/service_container.html#importing-configuration-with-imports
.. _`Dependency Injection Extension`: http://symfony.com/doc/current/book/service_container.html#importing-configuration-via-container-extensions
.. _`Episode 3`: http://knpuniversity.com/screencast/starting-in-symfony2-episode-3-2-1
.. _`Lukas points out`: http://pooteeweet.org/blog/1947/1962#m1962
.. _`JMSDebugginBundle`: http://jmsyst.com/bundles/JMSDebuggingBundle
.. _`screenshot`: http://screencast.com/t/J23luaL4Ii
.. _`Kris points out`: http://pooteeweet.org/blog/1947/1948#m1948
.. _`skinny controllers`: http://knpuniversity.com/screencast/dependency-injection/container#skinny-controllers-and-service-oriented-architecture
