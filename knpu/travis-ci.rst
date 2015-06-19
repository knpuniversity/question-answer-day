How to use Behat and Selenium on Travis CI
==========================================

From `spolischook`_

  How to connect Symfony2 project with Behat and Sahi to Travis Ci

Answer
------

.. note::

    Special thanks to our very-own `Roman`_ on this answer!

GREAT question, and one we've struggled and dealt with quite a bit over the
last few months. Fortunately, we have it working now - it's not always perfect,
but this should get your started.

Our goal will actually be to configure our ``.travis.yml`` file to execute
our Behat tests, some of which require Selenium. We like Selenium over
Sahi because it's very well-supported and generally seems to run just a bit
faster.

I'll assume that you already have Behat installed with a few `@javascript`
features you'd like to run and focus specifically on the `.travis.yml` configuration.
And if you're looking to sharpen your Behat skills (or start using it!),
check out `BDD, Behat, Mink and other Wonderful Things`_.

1) Installing a Web Server (e.g. Apache)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Your Travis server is an open canvas, upon which we computer-science artist
may paint whatever software and configuration we want. So... let's start
with a web server!

.. code-block:: yaml

    before_script:
        - sudo apt-get update > /dev/null
        - sudo apt-get install -y --force-yes apache2 libapache2-mod-php5 php5-curl php5-mysql php5-intl

.. note::

    Don't forget to install all other non-default extensions (i.e. php-ssl)

2) Give yourself a VirtualHost
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Since we'll be making real HTTP requests back to our application, we'll need
a VirtualHost setup. One easy way to do this is to leverage Apache's ``default``
VirtualHost, and use ``sed`` to stretch it to our needs:

.. code-block:: yaml

    before_script:
        # ...
        - sudo sed -i -e "s,/var/www,$(pwd)/web,g" /etc/apache2/sites-available/default
        - sudo /etc/init.d/apache2 restart

And if you want to use a specific domain, you can set that up too: just be
sure to do it before the apache restart call:

.. code-block:: yaml

    before_script:
        # ...
        - sudo sed -i -e "/DocumentRoot/i\ServerName knpu_qa.l" /etc/apache2/sites-available/default
        - echo "127.0.0.1 knpu_qa.l" | sudo tee -a /etc/hosts
        - sudo /etc/init.d/apache2 restart

3) Composer! And all the other Stuff
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Since Travis takes care of pulling your project into the server at the right
version, we just need to download any dependencies we have. We're using `Composer`_
and if you're not, you'll just need to tweak these commands to download your
libraries dependencies, however that may be:

.. tip::

    The ``composer`` executable is available on your Travis machine by default,
    but it may not be the latest version.

.. code-block:: yaml

    before_script:
        # ...

        # it may be useful to have the latest composer
        - composer self-update
        - composer install --dev --prefer-dist

The ``--prefer-dist`` part of Composer tells it to try to download zip archives,
instead of cloning the repositories of your dependencies. We've chosen to
do this because it's a lot faster. However, we've found if your packages
are hosted on GitHub, then you may see intermittent failures downloading
the packages. There's not much you can do here, but you may try ``--prefer-source``,
which will be slower, but *potentially* more reliable.

4) App-specific Stuff
---------------------

We now have a web server, a virtual host, our application and its dependencies
all ready to go. Now it's your turn to initialize the database, set any file
permissions, and anything else you may need to do before your application
is fully functional.

For Symfony2, the following code should do the trick (or at least get you
started):

.. code-block:: yaml

    before_script:
        # ...

        - app/console do:da:cr -e=test > /dev/null
        - app/console do:sc:cr -e=test > /dev/null
        - chmod -R 777 app/cache app/logs
        - app/console --env=test cache:warmup
        - chmod -R 777 app/cache app/logs

.. note::

    Yes, the double ``- chmod -R 777 app/cache app/logs`` is on purpose.
    Because multiple users will touch the cache files, we've had the most
    success warming all of the files and then once again making sure they're
    all writable.

5) The Selenium Magic
~~~~~~~~~~~~~~~~~~~~~

And finally, the step you've been waiting for: how the heck do I run Selenium
in this windowless machine? One solution that we've had success with is by
leverage a utility called ``xvfb``, or "X virtual framebuffer". It's actually
exactly what we want: it does everything that X does... but without there
actually being a window. Cool!

So let's get it all installed:

.. code-block:: yaml

    before_script:
        # ...

        - "sh -e /etc/init.d/xvfb start"
        - "export DISPLAY=:99.0"
        - "wget http://selenium.googlecode.com/files/selenium-server-standalone-2.31.0.jar"
        - "java -jar selenium-server-standalone-2.31.0.jar > /dev/null &"
        - sleep 5

The reason we need `sleep 5` at the end is because the selenium server takes
just a bit of time to initialize. If it's not ready when Behat starts, then
all related tests will fail for this build. Eek!

If you're curious about any more of this, check out the `GUI & Headless browser testing on travis-ci.org`_
by the Travis folks.

.. tip::

    You might want to use Chrome instead of the default (Firefox), since
    it's a bit faster and more stable. If so, try this:

    .. code-block:: yaml

        - "wget http://chromedriver.googlecode.com/files/chromedriver_linux32_23.0.1240.0.zip && unzip chromedriver_linux32_23.0.1240.0.zip && sudo mv chromedriver /usr/bin"

6) Running your tests
---------------------

Ok, let's do this! To run your tests... just run your tests! For example,
suppose we have some PHPUnit tests along with our Behat tests:

.. code-block:: yaml

    script:
        - phpunit path/to/tests
        - bin/behat

For Symfony2, this will look a bit different:

.. code-block:: yaml

    script:
        - phpunit -c app src/
        - bin/behat @KnpQABundle
        - bin/behat @KnpAnotherBundle

7) Other Issues and Improvements?
---------------------------------

I'll be honest, it's tough to get this stuff right, especially since you
can't shell directly to the server and look around. Phantom GitHub download
failures may also cause some heartache.

**Have you found some other tricks and secrets you want to share? Do it!**

Here are a few other complications you may encounter:

GitHub API Rate Limit
~~~~~~~~~~~~~~~~~~~~~

If you have a lot of dependencies, you may eventually see this awesome error
in your Travis output:

    Could not fetch https://api.github.com/repos/Behat/MinkGoutteDriver/zipball/v1.0.7,
    enter your GitHub credentials to go over the API rate limit

No worries! To fix this, you can use your own account to get a token that
your Travis build can use to get around this. We have this working here at
KnpUniversity.com, and we stole the whole idea from this blog:
`Creating and Using a Github OAuth Token With Travis And Composer`_.

The end-result is a ``.travis.composer.config.json`` file that looks like this:

.. code-block:: json

    {
       "config":{
          "github-oauth":{
             "github.com":"5675git-yer-own-key9854abc"
          }
       }
    }

and a new entry in ``.travis.yml`` before updating your composer dependencies:

.. code-block:: yaml

    before_script:
        # ...

        - "mkdir -p ~/.composer"
        - cp .travis.composer.config.json ~/.composer/config.json

8) Celebrate!
-------------

That's it! Crack open an ice-cold beer, spiced vanilla latte, cold water,
goat's milk, or whatever your preferred beverage and watch as Travis does
all the work for you.

But seriously, if you have any issues or improvements, post them for everyone!
Travis is still somewhat new, so it's a living process.

Cheers!

.. _`spolischook`: https://twitter.com/SPolischook
.. _`Composer`: https://knpuniversity.com/screencast/composer
.. _`GUI & Headless browser testing on travis-ci.org`: http://about.travis-ci.org/docs/user/gui-and-headless-browsers/
.. _`Creating and Using a Github OAuth Token With Travis And Composer`: http://blog.simplytestable.com/creating-and-using-a-github-oauth-token-with-travis-and-composer/
.. _`Roman`: https://twitter.com/Inoryy
.. _`BDD, Behat, Mink and other Wonderful Things`: https://knpuniversity.com/screencast/behat
