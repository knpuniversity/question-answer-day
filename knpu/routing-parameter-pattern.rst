More on Routing And Dependency Injection Parameters
===================================================

From `Dimka Mo`_

  How can I hide the pattern for a route - e.g. via parameter in ``parameters.yml``?
  My goal looks like: i have some line in ``parameters.yml``:

.. code-block:: yaml

    secret_url: /are/you/a/robot/

then in ``routing.yml`` something like this:

.. code-block:: yaml

    pattern: %secret_url%
    defaults: { ... }

I need to hide my url pattern from public, how can i do this? Thanks!

Answer
------

I don't think you were giving yourself enough credit with your question, because
you already know the answer! ;).

As we mentioned in the `Hostname Routing`_ chapter of our What's new in Symfony 2.2
tutorial, starting in Symfony 2.1, you can use a dependency injection parameter
anywhere in your routing.

First, let's start with a normal route:

.. code-block:: yaml

    # app/config/routing.yml
    # ...
    
    parameter_test:
        path: /are/you/a/robot
        defaults: { _controller: QADayBundle:Default:parameterTest }

The goal is to move the ``/are/you/a/robot`` part out of our code to somewhere
that's not committed. For many of you, that may be a strange requirement,
but the exercise here highlights a lot of nice things.

.. tip::

    And remember, there's nothing special about the ``parameters.yml`` file,
    except that we *choose* to put server-specific code in that file because
    we don't commit it to the repository (if this is new to you, see `Getting Started in Symfony2`_).

First, add a new parameter to your ``parameters.yml`` file:

.. code-block:: yaml

    # app/config/parameters.yml
    # ...

    my_hidden_url: /are/you/a/robot

To finish this off, simply reference it in your route:

.. code-block:: yaml

    # app/config/routing.yml
    # ...
    
    parameter_test:
        path: "%my_hidden_url%"
        defaults: { _controller: QADayBundle:Default:parameterTest }

That's it! But let's see what else we can do!

Using a Parameter as *part* of the routing Path
-----------------------------------------------

You can also leverage parameters as just a part of your routing path. To
show this off, create a new route to play with:

.. code-block:: yaml

    # app/config/routing.yml
    parameter_prefix:
        path: /admin/test
        defaults: { _controller: QADayBundle:Default:parameterTest }

If you had a lot of routes that began with the ``/admin`` prefix, you might
not want to repeat yourself. One solution of course is to import these routes
from an external routing file and use the `prefix key`_.

But you can also use parameters. This time, let's add a new parameter directly
to our ``config.yml`` file. I'm deciding to put it here instead of inside
``parameters.yml`` because this value isn't secret or server-specific:

.. code-block:: yaml

    # app/config/config.yml
    parameters:
        admin_prefix: /admin

We can now use this just like before, but now forming just a part of our
routing path:

.. code-block:: yaml

    # app/config/routing.yml
    parameter_prefix:
        path: "%routing_prefix%/test"
        defaults: { _controller: QADayBundle:Default:parameterTest }

Extra Credit: Where does this Magic Happen?
-------------------------------------------

Dependency injection parameters like ``%routing_prefix%`` are part of building
Symfony's service container: you define services and parameters, and when
the whole container is built, any strings surrounded by two ``%`` signs are
replaced by that parameter value.

But the engine that builds the service container is completely different
from the engine that compiles your routes together. So where do the two cross
over?

The answer is in the ``Router`` class that's used inside the Symfony Framework.
Symfony's `Routing Component`_ supplies a `Router`_ class which handles matching
and generating URLs. But when you use the Symfony Framework, the actual Router
object you're using lives in the FrameworkBundle. In fact, this is really
common, and you can see the class of these objects by finding the service
via the ``container:debug`` command:

.. code-block:: bash

    php app/console container:debug | grep -i router

.. code-block:: text

    router container Symfony\Bundle\FrameworkBundle\Routing\Router

If you scan the list, the ``router`` service should jump at you. Indeed,
the "router" used in the Symfony Framework is an instance of
`Symfony\\Bundle\\FrameworkBundle\\Routing\\Router`_.

The routing parameter magic happens in ``getRouteCollection``::

    public function getRouteCollection()
    {
        if (null === $this->collection) {
            $this->collection = $this->container
                ->get('routing.loader')
                ->load(
                    $this->resource,
                    $this->options['resource_type']
                );
            $this->resolveParameters($this->collection);
        }

        return $this->collection;
    }

This method is called early on when Symfony needs the full collection of
routes to use. The key here is that before returning the collection, the
`resolveParameters`_ function is called, which iterates over every route
in the collection and replaces parameters in the ``defaults``, ``path``,
``requirements`` and ``host`` keys of the route.

Why isn't this Slow?
~~~~~~~~~~~~~~~~~~~~

If you're wondering if iterating over every single route to replace this
parameter is slow, the answer is YES! But in reality, not at all :). In the
Symfony2 Framework, the final collection of routes is dumped to a physical
file that lives in your cache directory. It means that this process happens
once, then never again until your cache needs to be rebuilt.

Modifying Routes On-the-fly
~~~~~~~~~~~~~~~~~~~~~~~~~~~

You should never be in a hurry to extend Symfony and add a lot of magic to
it, but this is a great example of a way that you can do just that. Imagine
that there was some modification that you needed to make to every single
route in your system that couldn't be accomplished by leveraging a parameter.
One way to accomplish this would be to sub-class the ``Router`` class, override
``getRouteCollection``, and make your own changes.

... but for now I'll leave that as an exercise for you :).

.. _`Dimka Mo`: https://twitter.com/dimka_mo
.. _`Getting Started in Symfony2`: http://knpuniversity.com/screencast/getting-started-in-symfony2-2-1
.. _`prefix key`: http://symfony.com/doc/current/book/routing.html#prefixing-imported-routes
.. _`Routing Component`: http://symfony.com/doc/current/components/routing/introduction.html
.. _`Router`: https://github.com/symfony/symfony/blob/2.2/src/Symfony/Component/Routing/Router.php
.. _`Symfony\\Bundle\\FrameworkBundle\\Routing\\Router`: https://github.com/symfony/symfony/blob/2.2/src/Symfony/Bundle/FrameworkBundle/Routing/Router.php
.. _`resolveParameters`: https://github.com/symfony/symfony/blob/2.2/src/Symfony/Bundle/FrameworkBundle/Routing/Router.php#L85
.. _`Hostname Routing`: https://knpuniversity.com/screencast/new-symfony-2.2/host-routing
