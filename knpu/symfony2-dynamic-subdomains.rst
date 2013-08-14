How to handle dynamic Subdomains in Symfony
===========================================

From `Rafael`_:

  Hi, Symfony 2.2 has released hostname pattern for urls, I would like to
  know how can I create a url pattern that match domains loaded from a database?
  where should I put the code to load the domains and how should I pass this
  to a routing config file?

And from `zaherg`_:

  How can I handle auto generated subdomains routing with symfony 2?

Answer
------

Symfony 2.2 comes with :doc:`hostname handling</screencast/new-symfony-2.2/host-routing>`
out of the box, which lets you create two routes that have the same path,
but respond to two different sub-domains:

.. code-block:: yaml

    homepage:
        path: /
        defaults:
            _controller: QADayBundle:Default:index

    homepage_admin:
        path: /
        defaults:
            _controller: QADayBundle:Admin:index
        host: admin.%base_host%

The ``base_host`` comes from a value in ``parameters.yml``, which makes this
all even more flexible.

But what if you're creating a site that has dynamic sub-domains, where each
subdomain is a row in a "site" database table? In this case, the new ``host``
routing feature won't help us: it's really meant for handling a finite number
of concrete subdomains.

So how could this be handled? Let's find out together!

1) The VirtualHost
------------------

Before you go anywhere, make sure you have an Apache VirtualHost or Nginx
site that sends all the subdomains of your host to your application. Since
we're using ``lolnimals.l`` locally, we'll want ``*.lolnimals.l`` to be handled by
the VHost.

.. code-block:: apache

    <VirtualHost *:80>
      ServerName qaday.l
      ServerAlias *.qaday.l

      DocumentRoot "/Users/leannapelham/Sites/qa/web"
      <Directory "/Users/leannapelham/Sites/qa/web">
        AllowOverride All
        Allow from All
      </Directory>
    </VirtualHost>

Next, add a few entries to your ``/etc/hosts`` file for subdomains that
we can play with:

.. code-block:: text

    # /etc/hosts
    127.0.0.1       lolnimals.l kittens.lolnimals.l alpacas.lolnimals.l dinos.lolnimals.l

Great! Restart or reload your web server and then at least check that you
can hit your application from any of these sub-domains. So far our application
isn't actually doing any logic with these subdomains, but we'll get there!

2) Create the Site Entity
-------------------------

Next, let's use Doctrine to generate a new ``Site`` entity, which will store
all the information about each individual subdomain:

.. code-block:: bash

    php app/console doctrine:generate:entity

Give the entity a name of ``QADayBundle:Site``, which uses a ``QADayBundle``
that I already created. For fields, add one called ``subdomain`` and two others
called ``name`` and ``description``, so we at least have some basic information
about this site.

.. note::

    Press tab to take advantage of the command autocompletion. This is the
    brand new :ref:`2.2 autocomplete feature<symfony-cli-autocomplete>` in
    action.

Finish up the wizard then immediately create the database and schema. Be
sure to customize your ``app/config/parameters.yml`` file first:

.. code-block:: bash

    php app/console doctrine:database:create
    php app/console doctrine:schema:create

Finally, to make things interesting, I'll bring in a little data file that
will add two site records into the database::

    // load_sites.php
    require __DIR__.'/vendor/autoload.php';

    use Symfony\Component\HttpFoundation\Request;
    $loader = require_once __DIR__.'/app/bootstrap.php.cache';
    require_once __DIR__.'/app/AppKernel.php';
    $kernel = new AppKernel('dev', true);
    $request = Request::createFromGlobals();
    $kernel->boot();
    $container = $kernel->getContainer();
    $container->enterScope('request');
    $container->set('request', $request);

    // start loading things
    use KnpU\QADayBundle\Entity\Site;

    /** @var $em \Doctrine\ORM\EntityManager */
    $em = $container->get('doctrine')->getManager();
    $em->createQuery('DELETE FROM QADayBundle:Site')->execute();

    $site1 = new Site();
    $site1->setSubdomain('kittens');
    $site1->setName('Cute Kittens');
    $site1->setDescription('I\'m peerrrrfect!');

    $site2 = new Site();
    $site2->setSubdomain('alpacas');
    $site2->setName('Funny Alpacas');
    $site2->setDescription('Alpaca my bags!');

    $em->persist($site1);
    $em->persist($site2);
    $em->flush();

A better way to do this is with some real fixture files, but this will work
for now. This script bootstraps Symfony, but then lets us write custom code
beneath it. If you're curious about this script or fixtures, check out our
`Starting in Symfony2`_ series where we cover all this goodness and a ton
more.

Execute the script from the command line.

.. code-block:: bash

    php load_sites.php

I'll use the built-in `doctrine:query:sql` command to double-check that things
work.

.. code-block:: bash

    php app/console doctrine:query:sql "SELECT * FROM Site"

Great, let's get to the good stuff!

3) Finding the current Site the "Easy" Way
------------------------------------------

Because of our VirtualHost, our application already responds to every subdomain
of ``lolnimals.l``. The goal in our code is to be able to determine, based on
the host name, which Site record in the database is being used.

First, let's use a homepage route and controller that I've already created.
This will seem simple, but for now, let's determine which Site record is
being used by querying directly here. I'll add the ``$request`` as an argument
to the method to get the request object, then use ``getHost`` to grab the
host name. Dump the value to see that it's working::

    // src/KnpU/QADayBundle/Controller/DefaultController.php

    use Symfony\Component\HttpFoundation\Request;
    // ...

    public function indexAction(Request $request)
    {
        $currentHost = $request->getHttpHost();
        var_dump($currentHost);die;

        return $this->render('QADayBundle:Default:index.html.twig');
    }

The value stored in the database is actually *only* the subdomain part, not
the whole host name. In other words, we need to transform ``alpacas.lolnimals.l``
into simply ``alpacas`` before querying. Fortunately, I've already stored my
base host as a parameter in ``parameters.yml``:

.. code-block:: yaml

    # /app/config/parameters.yml
    parameters:
        # ...
        base_host:         qaday.l

By grabbing this value out of the container and doing some simple string
manipulation, we can get the current subdomain key::

    // src/KnpU/QADayBundle/Controller/DefaultController.php
    // ...

    public function indexAction(Request $request)
    {
        $currentHost = $request->getHttpHost();
        $baseHost = $this->container->getParameter('base_host');

        $subdomain = str_replace('.'.$baseHost, '', $currentHost);
        var_dump($subdomain);die;

        return $this->render('QADayBundle:Default:index.html.twig');
    }

Perfect! Now querying for the current Site is pretty easy. We'll also assume
that we *need* a valid subdomain - so let's show a 404 page if we can't find
the Site::

    // src/KnpU/QADayBundle/Controller/DefaultController.php
    // ...

    $site = $this->getDoctrine()
        ->getRepository('QADayBundle:Site')
        ->findOneBy(array('subdomain' => $subdomain))
    ;
    if (!$site) {
        throw $this->createNotFoundException(sprintf(
            'No site for host "%s", subdomain "%s"',
            $baseHost,
            $subdomain
        ));
    }

Finally, pass the ``$site`` into the template so we can prove we're matching
the right one::

    // src/KnpU/QADayBundle/Controller/DefaultController.php
    // ...

    return $this->render('QADayBundle:Default:index.html.twig', array(
        'site' => $site,
    ));

Dump some basic information out in the template to celebrate:

.. code-block:: html+jinja

    {# src/KnpU/QADayBundle/Resources/views/Default/index.html.twig #}
    {%  extends '::base.html.twig' %}

    {% block body %}
        <h1>Welcome to {{ site.name }}</h1>

        <p>{{ site.description }}</p>
    {% endblock %}

Ok, try it out! The ``alpacas`` and ``kittens`` subdomains work perfectly, and the
``dinos`` subdomain causes a 404, since there's no entry in the database for
it.

This is simple and functional, but let's do better!

4) The Site Manager
-------------------

We've met our requirements of dynamic sub-domains, but it's not very pretty
yet. We'll probably need to know what the current Site is all over the
place in our code - in every controller and in other places like services.
And we certainly don't want to repeat all of this code, that would be crazy!

Let's fix this, step by step. First, create a new class called ``SiteManager``,
which will be responsible for always knowing what the current Site is. The
class is very simple - just a property with a get/set method::

    // src/KnpU/QADayBundle/Site/SiteManager.php
    namespace KnpU\QADayBundle\Site;

    class SiteManager
    {
        private $currentSite;

        public function getCurrentSite()
        {
            return $this->currentSite;
        }

        public function setCurrentSite($currentSite)
        {
            $this->currentSite = $currentSite;
        }
    }

Next, register this as a service. If services are a newer concept for you,
we cover them extensively in `Episode 3 of our Symfony2 Series`_. I'll create
a new ``services.yml`` file in my bundle. The actual service configuration
couldn't be simpler:

.. code-block::  yaml

    # src/KnpU/QADayBundle/Resources/config/services.yml
    services:
        site_manager:
            class: KnpU\QADayBundle\Site\SiteManager

This file is new, so make sure it's imported. I'll import it by adding a
new ``imports`` entry to ``config.yml``:

.. code-block:: yaml

    # app/config/config.yml
    imports:
        # ...
        - { resource: "@QADayBundle/Resources/config/services.yml" }

Sweet! Run ``container:debug`` to make sure things are working:

.. code-block:: bash

    php app/console container:debug | grep site

.. code-block:: text

    site_manager   container KnpU\QADayBundle\Site\SiteManager

Perfect! So.... how does this help us? First, let's set the current site on
the ``SiteManager`` from within our controller::

    // src/KnpU/QADayBundle/Controller/DefaultController.php
    // ...
    
    /** @var $siteManager \KnpU\QADayBundle\Site\SiteManager */
    $siteManager = $this->container->get('site_manager');
    $siteManager->setCurrentSite($site);

    return $this->render('QADayBundle:Default:index.html.twig', array(
        'site' => $siteManager->getCurrentSite(),
    ));

Don't let this step confuse you, because it's pretty underwhelming.
This sets the current site on the ``SiteManager``, which we use immediately
to pass to the template. If this looks kinda dumb to you, it is! Getting the 
current site from the ``SiteManager`` is cool, but the problem is that we 
still need to set this manually.

In other words, the ``SiteManager`` is only one piece of the solution. Now,
let's add an event listener to fix the rest.

5) Determining the Site automatically with an Event Listener
------------------------------------------------------------

Somehow, we need to be able to move the logic that determines the current
Site out of our controller and to some central location. To do this, we'll
leverage an event listener. Again, if this is new to you, we cover it in
`Episode 3 of our Symfony2 Series`_.

First, create the listener class, let's call it ``CurrentSiteListener`` and
set it to have the ``SiteManager`` and Doctrine's ``EntityManager`` injected
as dependencies. Let's also inject the ``base_host`` parameter, we'll need
it here as well::

    // src/KnpU/QADayBundle/EventListener/CurrentSiteListener.php
    namespace KnpU\QADayBundle\EventListener;

    use KnpU\QADayBundle\Site\SiteManager;
    use Doctrine\ORM\EntityManager;

    class CurrentSiteListener
    {
        private $siteManager;

        private $em;

        private $baseHost;

        public function __construct(SiteManager $siteManager, EntityManager $em, $baseHost)
        {
            $this->siteManager = $siteManager;
            $this->em = $em;
            $this->baseHost = $baseHost;
        }
    }

The goal of this class is to determine and set the current site at the very
beginning of every request, before your controller is executed. Create a
method called ``onKernelRequest`` with a single ``$event`` argument, which
is an instance of ``GetResponseEvent``::

    // src/KnpU/QADayBundle/EventListener/CurrentSiteListener.php
    
    // ...
    use Symfony\Component\HttpKernel\Event\GetResponseEvent;

    class CurrentSiteListener
    {
        // ...

        public function onKernelRequest(GetResponseEvent $event)
        {
            die('test!');
        }
    }

.. tip::

    The Symfony.com documentation has a full list of the events and event
    objects in the `HttpKernel`_ section.

Before we fill in the rest of this method, register the listener as a service
and tag it so that it's an event listener on the ``kernel.request`` event:

.. code-block:: yaml

    services:
        # ...

        current_site_listener:
            class: KnpU\QADayBundle\EventListener\CurrentSiteListener
            arguments:
                - "@site_manager"
            tags:
                -
                    name: kernel.event_listener
                    method: onKernelRequest
                    event: kernel.request

And with that, let's try it! When we refresh the page, we can see the message
that proves that our new listener is being called early in Symfony's bootstrap.

With all that behind us, let's fill in the final step! In the ``onKernelRequest``
method, our goal is to determine and set the current site. Copy the logic
out of our controller into this method, then tweak things to hook up::

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $currentHost = $request->getHttpHost();
        $subdomain = str_replace('.'.$this->baseHost, '', $currentHost);

        $site = $this->em
            ->getRepository('QADayBundle:Site')
            ->findOneBy(array('subdomain' => $subdomain))
        ;
        if (!$site) {
            throw new NotFoundHttpException(sprintf(
                'No site for host "%s", subdomain "%s"',
                $this->baseHost,
                $subdomain
            ));
        }

        $this->siteManager->setCurrentSite($site);
    }

The differences here are a bit subtle. For example, the ``baseHost`` is now
stored in a property and we can get Doctrine's repository through the ``$em``
property. We've also replaced the ``createNotFoundException`` call by instantiating
a new ``NotFoundHttpException`` instance. The ``createNotFoundException``
method lives in Symfony's base controller. We don't have access to it here,
but this is actually what it really does behind the scenes.

Since we've registered this as an event listener on the ``kernel.request``
event, this method will guarantee that the ``SiteManager`` has a current site
before our controller is ever executed. This means we can get rid of almost
all of the code in our controller::

    public function indexAction()
    {
        /** @var $siteManager \KnpU\QADayBundle\Site\SiteManager */
        $siteManager = $this->container->get('site_manager');

        return $this->render('QADayBundle:Default:index.html.twig', array(
            'site' => $siteManager->getCurrentSite(),
        ));
    }

Try it out! Sweet, it still works! We can now use the ``SiteManager`` from
anywhere in our code to get the current Site object. For example, if we needed
to load all the blog posts for only this Site, we could grab the current Site
then create a query that returns only those items. Basically, from here, you
can be dangerous!

.. _`Rafael`: https://twitter.com/dextervip
.. _`zaherg`: https://twitter.com/zaherg
.. _`Starting in Symfony2`: http://knpuniversity.com/screencast/getting-started-in-symfony2-2-1
.. _`Episode 3 of our Symfony2 Series`: http://knpuniversity.com/screencast/starting-in-symfony2-episode-3-2-1
.. _`HttpKernel`: http://symfony.com/doc/current/components/http_kernel/introduction.html#creating-an-event-listener