Creating your very own Composer Package
=======================================

From: `Marcin Grochulski`_

  I wonder how to create your own bundle and then add it as an installation
  package for the Composer.

Answer
------

This is a *great* Composer question, and will let us walk through the lifecycle
of a library and how it works with Composer. Be sure to check out our free
`Wonderful World of Composer`_ screencast first before diving in here.

Let's suppose that we have a library or Symfony2 Bundle, and we'd like to
release this open source and then include it in our projects. You can do
this at a number of different levels of sophistication. Let's walk through
it!

Step 1: Put your Library on GitHub
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Before anything else, put your library on GitHub. Seriously, if you *only*
did this, then people could already begin using your library.

In fact, I've just created a wonderful new library that does... well, nothing
honestly - but it'll serve as our example: https://github.com/weaverryan/derp-dangerzone.

The library is up on GitHub, and in real life would actually have some useful
things. You'll also see a ``composer.json`` file. ignore it and pretend it
isn't there for now.

Now suppose that we want to include that library in one of our projects.
If the new library were registered with `Packagist`_ (we'll add it eventually),
then it would be as simple as adding one line to our ``require`` key in ``composer.json``.

But since it's not, we have to do the work ourself using a custom `repositories`_
key in the ``composer.json`` or our *project*:

.. code-block:: json

    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "weaverryan/derp-dangerzone",
                "version": "dev-master",
                "source": {
                    "url": "git://github.com/weaverryan/derp-dangerzone.git",
                    "type": "git",
                    "reference": "master"
                },
                "autoload": {
                    "psr-0" : {
                        "Weaverryan\\DangerZone" : "src"
                    }
                }
            }
        }
    ],

.. tip::

    The ``repositories`` key sits at the root of your ``composer.json`` file,
    as a sibling to (i.e. next to) the ``require`` key.

Wow, that was a lot of work! The problem is that the ``derp-dangerzone``
doesn't have a ``composer.json`` file yet (well, we're pretending it doesn't),
so we have to manually define the package ourselves. There are a few interesting
parts:

* ``version``: Our library doesn't really have versions yet, so we create
  a single version that points to the ``master`` branch (see the ``reference``
  key). If we had a real version, we might define something like ``2.0.0``
  here and update the ``reference`` below to point at a branch or tag.

* ``autoload``: Most libraries follow the `PSR-0`_ naming standard, including
  our new library. The only class in the library is in the ``Weaverryan\DangerZone``
  namespace and is called ``HalloThere``. Accordingly, once you're in the
  ``src/`` directory, it lives at ``Weaverryan/DangerZone/HalloThere.php``.
  Under this key, we tell Composer that all of our classes will live in
  the ``Weaverryan\Dangerzone`` namespace and to start looking for them
  in the ``src/`` directory.

With this new entry, Composer now sees a fully valid package called ``weaverryan/derp-dangerzone``
with a single ``dev-master`` version. In other words, just add it to your
``require`` key in the ``composer.json`` of your *project*:

.. code-block:: json

    "require": {
        "... other libraries": "... other version",

        "weaverryan/derp-dangerzone": "dev-master"
    },

Update as you normally do:

.. code-block:: bash

    php composer.phar update

Phew! That was a lot of work. But as we make our library more official, most
of the work is behind us!

Step 2: Give your Library a composer.json File
----------------------------------------------

If everyone that uses your library needs to do all that work, you can bet
that you won't be very popular. To fix this, we'll need to put a ``composer.json``
file in the library itself. Fortunately, this is really easy, and we can basically
move the ``package`` we already created into a new ``composer.json`` file
at the root of our library. To make it easier, you can remove the ``version``
and ``source`` keys - Composer will look at your branches and tags to get
all of this.

In other words, create a ``composer.json`` file in your *library*:

.. code-block:: json

    {
        "name": "weaverryan/derp-dangerzone",
        "autoload": {
            "psr-0" : {
                "Weaverryan\\DangerZone" : "src"
            }
        }
    }

And this is exactly what you see right now at `weaverryan/derp-dangerzone`_.
At this point, the `Packagist`_ repository doesn't know about our library,
but our library *does* have a ``composer.json`` file. This is a *huge* step
forward, because it lets us simplify our *project's* composer.json quite
a bit. We still need a custom ``repositories`` key, but now it's much simpler.

Update your *projects's* ``composer.json`` to have the following:

.. code-block:: json

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/weaverryan/derp-dangerzone"
        }
    ],

Now, instead of a ``packages`` key, we have a simpler `vcs`_ key, which basically
says: "go over to this repository and consume its ``composer.json`` file".

Step 3: Registering with Packagist
----------------------------------

As we've seen, creating a ``composer.json`` file in your library is optional,
but makes using it much much easier. The next and last step to simplicity
is to register it with Packagist. This is the easiest step yet and involves
filling in a few forms at `Packagist`_ and waiting for it to crawl your repository.

Once you've registered your library with Packagist (and it's been crawled),
your library can be used by adding a single entry to the ``require`` key
of a ``composer.json`` file: no extra ``repositories`` entry is needed:

.. code-block:: json

    "require": {
        "... other libraries": "... other version",

        "weaverryan/derp-dangerzone": "dev-master"
    },

That's it! The process is simple, but nice to walk through. Now start sharing
your code!

.. _`Marcin Grochulski`: https://twitter.com/MGrochulski
.. _`Wonderful World of Composer`: http://knpuniversity.com/screencast/composer
.. _`Packagist`: https://packagist.org/
.. _`repositories`: http://getcomposer.org/doc/05-repositories.md
.. _`PSR-0`: https://speakerdeck.com/weaverryan/the-wonderful-world-of-symfony-components?slide=31
.. _`weaverryan/derp-dangerzone`: https://github.com/weaverryan/derp-dangerzone
.. _`vcs`: http://getcomposer.org/doc/05-repositories.md#vcs