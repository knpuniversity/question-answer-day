How to compile .less styles into .css (on any OS)
=================================================

From `dextervip`_

  Hi, Less language have been growing up a lot but How can I configure assetic
  manager to compile less css and rewrite it properly in windows environment?

Answer
------

.. note::

    Special thanks to our very-own `Roman`_ on this answer!

We use `less`_ in our projects and love it. However, we do have a mixture
of operating systems and also had our own issues getting less to compile
properly.

Less is typically compiled by ``lessc``, which is installed from ``npm``
(Node Package Manager), which is a part of Node.js. Phew! Now, none of this
is necessarily complicated, but if you're not familiar with node and node
modules, then it can be a blocker. As the question suggested, this is sometimes
even harder on Windows. In fact, Rafael - who asked this question - has his
`own problems`_ with exactly this.

So what's the solution? Our advice: avoid the problem.

What we mean is to avoid the true less and instead use `lessphp`_ - a pure
PHP implementation of the less compiler. Normally, I'm a proponent of letting
other languages do things they're good at, but if you're having issues with
normal less, take advantage of this tool. As an added bonus, ``lessphp``
has a built-in filter in Assetic, so using it is simple.

.. note::

    While lessphp is very good, nothing is as good as the real thing and
    it's possible that you'll write valid ``less`` code that doesn't compile
    correctly. However, these seem to be edge-cases, so worry about that
    when it happens.

To install ``lessphp``, just add it to your ``composer.json`` file under
the ``require`` key:

.. code-block:: json

    "leafo/lessphp": "~0.3"

.. tip::

    Curious about the ``~0.3`` version? It's roughly equivalent to ``>=0.3,<1.0``
    and is awesome. See `Package Versions`_ for more details.

Next, configure the ``assetic`` key on ``config.yml`` to activate the filter:

.. code-block:: yaml

    # app/config/config.yml
    # ...

    assetic:
        filters:
            lessphp:
                file: %kernel.root_dir%/../vendor/leafo/lessphp/lessc.inc.php
                apply_to: "\.less$"

.. tip::

    Unlike most libraries we bring in via Composer, this one does *not* follow
    the PSR-0 standard, and actually just contains a single (useful) file.
    The ``file`` key under assetic filters is built to handle this: the file
    is required before the filter is used.

Finally, setup the stylesheets in your base layout (or wherever):

.. code-block:: html+jinja

    {# app/Resources/view/base.html.twig #}
    {# ... #}

    {% stylesheets filter='lessphp' output='css/main.css' 
        'bundles/qaday/less/main.less' 
    %}
        <link href="{{ asset_url }}" type="text/css" rel="stylesheet" media="all" />
    {% endstylesheets %}

.. tip::

    You only need either the ``apply_to`` in ``config.yml`` *or* the
    ``filter='lessphp'`` in your template, but not both! With the ``apply_to``
    option, the filter is automatically applied to all ``*.less`` files.

Woh! That's it! Assuming you have the ``use_controller`` setting on in ``config_dev.yml``,
you can just access your page to see it working. In the background, the ``main.less``
file is being processed and the end-CSS is being returned.

You can also dump your assets and see a shiny-new ``main.css`` file come out:

.. code-block:: bash

    php app/console assetic:dump --env=prod

If you ever have any weird issues - especially when playing with your ``assetic``
configuration in ``config.yml``, try clearing your Symfony *and* browser
cache. You don't normally need to do this, but there are some edge cases in
this area where you might need to.

.. tip::

    If your CSS files begin to load slowly in the ``dev`` environment, you
    may consider turning the ``use_controller`` setting to ``false`` and
    dumping your assets manually with the ``--watch`` flag. See
    `Starting in Symfony2 Episode 4`_

.. _`less`: http://lesscss.org/
.. _dextervip: https://twitter.com/dextervip
.. _`own problems`: https://github.com/symfony/AsseticBundle/issues/155
.. _`lessphp`: http://leafo.net/lessphp/
.. _`Package Versions`: http://getcomposer.org/doc/01-basic-usage.md#package-versions
.. _`PSR-0`: http://phpmaster.com/autoloading-and-the-psr-0-standard/
.. _`Starting in Symfony2 Episode 4`: http://knpuniversity.com/screencast/starting-in-symfony2-episode-4-2-1
.. _`Roman`: https://twitter.com/Inoryy
