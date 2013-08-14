Swiftmailer Spooling and Handling Failures
==========================================

From `Philipp Rieber`_

    Hi, I'm using swiftmailer's file spooling and I'm flushing the queue
    every minute using a cron task:
    
    .. code-block:: bash

        app/console swiftmailer:spool:send --env=prod > /dev/null 2>>app/logs/error.log

    Due to SMTP errors like "554 Message rejected: Address blacklisted" or
    "554 Message rejected: Email address is not verified" some message files
    remain in the spool directory and swiftmailer tries to send them over
    and over again following the "recovery-timeout" setting of the command
    (default = 15 minutes).

    The problem is that a single exception during the sending process cancels
    the whole command. So if there are more than 15 "xxx.message.sending"
    files in the spool directory after a while and the cron job runs every
    minute with a recovery-timeout of 15 minutes, then the new messages won't
    get sent any more. How can I handle that? Do I need an additional command
    to remove old "xxx.message.sending" files, e.g by wrapping and extending
    the ``swiftmailer:spool:send`` command?

    Currently I remove the old files manually from time to time and according
    to Google I'm the only one having this issue ;-)

    Thank you!

Answer
------

Woh, tough question! So, let's see what we can do. First, let's me give everyone
else a little background by building a test project. Even if you're not having
this issue, we're going to learn quite a bit about spooling and some lower-level
parts of Swift Mailer. Philipp, you can skip down to the answer, or suggested
approach for this difficult problem ;).

First, configure Swift Mailer to send emails in some way, and tell it to
use a "file" spool. If you haven't seen this before, we have a cookbook article
on it at Symfony.com called, well, `How to Spool Emails`_:

.. code-block:: yaml

    # app/config/config.yml
    swiftmailer:
        transport: %mailer_transport%
        host:      %mailer_host%
        username:  %mailer_user%
        password:  %mailer_password%
        spool:     { type: file }

By default, most of the ``swiftmailer`` configuration is stored in the
``app/config/parameters.yml`` file, so make sure you update your settings
there.

File spooling is really easy, and kinda neat. Whenever you tell Swiftmailer
to send an email, it actually doesn't. Instead it stores it in a file and
waits for you to run a Symfony task that actually sends the email. The obvious
advantage is that the experience for your end-user is much faster.

Let's use a small script I've created that loads up a bunch of spooled messages
for us. This bootstraps Symfony and lets me write any Symfony code I want
in it. It's a quick and dirty way to create a spot where we can execute some
code that needs Symfony and is something we cover in our `Starting in Symfony2 series`_::

    <?php
    // load_emails.php
    require __DIR__.'/vendor/autoload.php';

    use Symfony\Component\HttpFoundation\Request;
    $loader = require_once __DIR__.'/app/bootstrap.php.cache';
    require_once __DIR__.'/app/AppKernel.php';
    $kernel = new AppKernel('prod', true);
    $request = Request::createFromGlobals();
    $kernel->boot();
    $container = $kernel->getContainer();
    $container->enterScope('request');
    $container->set('request', $request);
    /* end bootstrap */

    /** @var $mailer \Swift_Mailer */
    $mailer = $container->get('mailer');

    $message = \Swift_Message::newInstance()
        ->setSubject('Testing Spooling!')
        ->setFrom('hello@knpuniversity.com')
        ->setTo('ryan@knplabs.com')
        ->setBody('Hallo emails!')
    ;

    for ($i = 0; $i < 10; $i++) {
        $mailer->send($message);
    }

The script sends 10 email messages. Behind the scenes, I'll also add a little
bit of code to the core os Swift Mailer so that my SMTP server appears to
fail about every 5 sends. This will fake STMP sending errors::

    // vendor/swiftmailer/swiftmailer/lib/classes/Swift/Transport/AbstractSmtpTransport.php
    // ...

    protected function _assertResponseCode($response, $wanted)
    {
        list($code) = sscanf($response, '%3d');

        if (rand(1, 5) == 5 && in_array(250, $wanted)) {
            $code = 554;
        }
        
        // ... the rest of the function
    }

How Emails are File Spooled
---------------------------

Run this script from the command line to queue the 10 messages:

.. code-block:: bash

    php load_emails.php

.. tip::

    The script runs in the ``prod`` environment to be more realistic (since
    your site typically runs in the ``prod`` environment). So, be sure to
    clear your ``prod`` cache before trying any of this:
    
    .. code-block:: bash
    
        php app/console cache:clear --env=prod

You won't see anything visually, and no emails were sent, but if you look
in the cache directory, you should see a ``swiftmailer`` directory with a
single file for each spooled message:

.. code-block:: bash

    ls -la app/cache/prod/swiftmailer/spool

.. code-block:: text

    0Mo4LSRwTj.message
    30MJF9qOP7.message
    BLxbfA_cKs.message
    BaW2_ZzpAE.message
    CgyPxTQ59E.message
    Fw_Bux5LUh.message
    GsDgqNHc89.message
    IDbFa9CCtB.message
    LEw9Xe.EZY.message
    RKbbDMVKu9.message

This is how the file spool works: each message is given a random filename
and its contents are a serialized version of the ``Swift_Message``.

To actually send these emails, use the ``swiftmailer:spool:send`` command.

.. code-block:: bash

    php app/console swiftmailer:spool:send --env=prod --message-limit=10

Under normal conditions, this would find the first 10 files in the ``spool``
directory, unserialize each file's contents and then send it. In fact, behind
the scenes, each file is suffixed with ``.sending`` the moment before it
is sent, and then deleted afterwards if everything went ok. If you watched
your ``spool`` directory closely, you could see this while it's sending:

.. code-block:: text

    0Mo4LSRwTj.message.sending
    30MJF9qOP7.message
    BLxbfA_cKs.message
    BaW2_ZzpAE.message
    CgyPxTQ59E.message
    Fw_Bux5LUh.message
    GsDgqNHc89.message
    IDbFa9CCtB.message
    LEw9Xe.EZY.message
    RKbbDMVKu9.message

Normally you don't really care about this... until your emails start to fail.

How Swift Mailer handles Failures
---------------------------------

As Philipp mentioned, when you run the ``swiftmailer:spool:send`` command
and one email fails, it will blow up! That's actually not that big of a problem
initially: as soon as any email is sent successfully, its spool file is deleted,
which avoids duplicate sending, even if another email send blows up later.
The email that failed remains in its "sending" state, meaning it has the
``.sending`` suffix:

.. code-block:: text

    0Mo4LSRwTj.message.sending

When you re-run the command, that ``.sending`` file is skipped, and the other
nine files in the spool are sent.

So then, what happens to the email that failed? Does Swift Mailer every try
to send it again? In fact, it does! And this is where the problems start.
When you run the command, there is an optional ``--recover-timeout`` option,
which defaults to 900, or 15 minutes. This option means that if a file has
been in the ``.sending`` state for 15 minutes, the suffix should be removed
and we should try re-sending it. This is really smart, because it means that
if your SMTP server has a temporary failure, the email will just send later.

Failures, Failures Blocking Everything!
---------------------------------------

But sometimes, an email fails to send for a permanent reason, like
``554 Message rejected: Address blacklisted``. No matter how many times you
try to re-send that email, it will probably never work. It will fail, wait
fifteen minutes, fail again, then repeat endlessly. Even if these happen
every now and then, after awhile you'll get a ``spool/`` directory that's
full of failures:

.. code-block:: text

    0Mo4LSRwTj.message.sending
    30MJF9qOP7.message.sending
    BLxbfA_cKs.message.sending
    BaW2_ZzpAE.message.sending
    CgyPxTQ59E.message.sending
    Fw_Bux5LUh.message.sending
    GsDgqNHc89.message.sending
    IDbFa9CCtB.message.sending
    LEw9Xe.EZY.message.sending
    RKbbDMVKu9.message.sending

These are just annoying at first, since after fifteen minutes, each is re-tried,
which causes your script to fail and no other emails to be sent. If you're
running the script often enough, it's no big deal.

So back to Philipp's question: 

  So if there are more than 15 "xxx.message.sending" files in the spool directory
  after a while and the cron job runs every minute with a recovery-timeout
  of 15 minutes, then the new messages won't get sent any more. How can I
  handle that?

Let's walk through this: imagine you have 15 files that are failing. One-by-one,
these become eligible to be re-tried. Our script, which runs every minute,
tries one, then fails. A minute later it tries another, then another, etc,
etc. After fifteen minutes it hasn't actually sent any emails - it's only
failed to re-send these. To make matters worse, the first failed email is
ready to be re-tried again, so the cycle continues.

The Solution?
-------------

This is actually a really interesting, but challenging issue. At the core
is the fact that Swift Mailer can't tell the difference between a mail that
should be re-tried, and one that will fail forever. To make matters worse,
there's no possible way to configure the file spool to stop trying after a
few attempts and delete the mail. This seems like a shortcoming in the spool
itself, but for now, let's work around it!

In my opinion, the best solution is create a separate task that handles these
failures by trying them once more, then deleting them finally. Let's start
with the skeleton for the command::

    namespace KnpU\QADayBundle\Command;

    use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class ClearFailedSpoolCommand extends ContainerAwareCommand
    {
        protected function configure()
        {
            $this
                ->setName('swiftmailer:spool:clear-failures')
                ->setDescription('Clears failures from the spool')
            ;
        }

        protected function execute(InputInterface $input, OutputInterface $output)
        {
        }
    }

The goal of the command will be to find all ``.loading`` files, try them
once again, then delete the spool file. This will use a few parts of Swift
Mailer and its integration with Symfony that are deep enough that you'll
need to be more careful when you upgrade. For example, the fact that the failed
spools are suffixed with ``.sending`` is really a detail that we're not supposed
to care about, but we'll take advantage of it.

To start, grab the *real* transport from the service container and make sure
it's started::

    /** @var $transport \Swift_Transport */
    $transport = $this->getContainer()->get('swiftmailer.transport.real');
    if (!$transport->isStarted()) {
        $transport->start();
    }

The "transport" used by the ``mailer`` service is the file spool, which means
when you send through it, it actually just spools. Symfony stores your *real*
transport - whether that be SMTP or something else - as a service called
``swiftmailer.transport.real``.

Next, let's find all the spooled files. This takes advantage of the ``swiftmailer.spool.file.path``
parameter, which contains the directory where the spool files live. This
parameter is used when the `Swift_FileSpool is instantiated`_. We'll also
use the `Finder`_ component to really make this shine::

    // ...
    $spoolPath = $this->getContainer()->getParameter('swiftmailer.spool.file.path');
    $finder = Finder::create()->in($spoolPath)->name('*.sending');

    foreach ($finder as $failedFile) {
        // ... 
    }

Finally, fill in the loop::

    // ...
    foreach ($finder as $failedFile) {
        // rename the file, so no other process tries to find it
        $tmpFilename = $failedFile.'.finalretry';
        rename($failedFile, $tmpFilename);

        /** @var $message \Swift_Message */
        $message = unserialize(file_get_contents($tmpFilename));
        $output->writeln(sprintf(
            'Retrying <info>%s</info> to <info>%s</info>',
            $message->getSubject(),
            implode(', ', array_keys($message->getTo()))
        ));

        try {
            $transport->send($message);
            $output->writeln('Sent!');
        } catch (\Swift_TransportException $e) {
            $output->writeln('<error>Send failed - deleting spooled message</error>');
        }

        // delete the file, either because it sent, or because it failed
        unlink($tmpFilename);
    }

Woh! Let's walk through this using 4 friendly bullet points:

1) We rename the spool file to prevent any other process from sending this
file while we try;

2) The contents of the spool file are a serialized ``\Swift_Message`` object,
which we an unserialize to get it back;

3) We once again try to ``send`` the message.

4) Whether the message sends or fails, we delete the spool file to clean
it out.

And that's it! Now, set the command to run on some interval. If these messages
tend to start to be a problem after an hour, run this hourly. If it's an
uncommon issue, run it daily:

.. code-block:: bash

    php app/console swiftmailer:spool:clear-failures --env=prod

With a good mixture of failures and success, the output will look something
like this:

.. code-block:: text

    Retrying Testing Spooling! to ryan@knplabs.com
    Sent!
    Retrying Testing Spooling! to ryan@knplabs.com
    Send failed - deleting spooled message
    Retrying Testing Spooling! to ryan@knplabs.com
    Sent!
    Retrying Testing Spooling! to ryan@knplabs.com
    Send failed - deleting spooled message

There are countless other approaches you could take, but I prefer this one
because it prevents you from needing to override any core code. The point
is that, one way or another, you're on your own when you solve this. With
some refactoring of ``Swift_FileSpool``, it should be possible to set a max
retry limit per mail, but that's not the case right now.

Still, file spooling is great. If you're concerned about delivering emails
to your users without slowing down their experience, this is a very easy
way to accomplish that.

.. _`Philipp Rieber`: https://twitter.com/bicpi
.. _`How to Spool Emails`: http://symfony.com/doc/current/cookbook/email/spool.html
.. _`Starting in Symfony2 series`: http://knpuniversity.com/screencast/getting-started-in-symfony2-2-1
.. _`Swift_FileSpool is instantiated`: https://github.com/symfony/SwiftmailerBundle/blob/master/Resources/config/spool_file.xml#L12
.. _`Finder`: http://symfony.com/doc/current/components/finder.html