Symfony2: Setup, Configuration, Rad?
====================================

From Andy:

  As Symfony becomes more powerful and scalable, is that at the expense of
  being less useful for small rapid developments - given the large number
  of dependencies and configuration required on each project? (Setting up
  user login, media management, content repository, Gaufrette, admin generator,
  etc.).

Answer
------

Woh! This is a tough question, but one that certainly affects us every day.

There are probably a lot of ways to tell the story of RAD versus "enterprise",
but let's look at Symfony. Symfony1 was basically a port of early Ruby on Rails:
convention over configuration, RAD, etc. The goal was to develop things faster,
with less repetition, and the results were revolutionary.

Fast forward 5 years to Symfony2. Short-cuts have been replaced with best-practices
and convention replaced with predictability and explicitness.

The end result is that developing a feature may be faster in symfony1 or
something like it. Conversely, that feature is probably cleaner and more
maintainable inside Symfony2 due to better practices and less shortcuts
(leading to less wtf moments).

This isn't the end of the story (keep reading), but it does mean that you
need to choose the right tool for the job. If you're the only developer on
a small project, it might be better to choose Silex or something smaller.
If you're building something more complex, Symfony2 becomes a more clear winner.

RAD Versus Quality? Both?
-------------------------

Symfony1 and other "RAD" PHP frameworks use a lot of bad practices and magic
whereas Symfony2 fixes that but is less RAD out-of-the-box.

So, can we have RAD *and* high-quality tools. 

The answer is a resounding YES, though we're not totally there yet.

The topic actually came up very recently in a blog post by Lukas Smith called
`Good design is no excuse for wasting time`_. Going back to our history lesson,
symfony1 may have been RAD, but its architecture was fundamentally flawed
and coupled. Fixing it meant re-building correctly form the ground-up. This
doesn't mean that we *can't* also be RAD, it just means that RAD tools need
to be built on top of Symfony2.

And while it's true that there's a lot of integration still to worry about
between user management, asset management, Gaufrette, admin areas, etc, you
*do* have some options, which Lukas points out:

1) `KnpRadBundle`_

A *lot* of love at Knp has been put into this little project, which takes
the Symfony2 framework experience and makes it opinionated. Things are integrated
more naturally and there are plenty of shortcuts. But, it's still the Symfony2
framework, so you're not learning something new, just opting into RAD features.

2) `Laravel`_

Laravel4 is being built on top of Symfony2, and while I haven't tried it
out yet, my impression is that it lowers the Symfony2 learning curve (and
I'm assuming also adds some RAD). This is another great example of taking
our new solid core and making it quicker to develop things.

3) `Silex`_

Silex is the micro-framework built on top of Symfony2, which lets you get
an application going instantly. It's not suitable for everything, and eventually
you'll wish you had more tools, but you'll get started *fast*.

There are several other things, which provide pieces to complete the puzzle
(e.g. `SonataAdminBundle`_, `FOSRestBundle`_), but more work certainly needs
to be done to bring all of these great pieces together into one, harmonic -
and more opinionated - piece. It's a work-in-progress, but it's not a reason
to *not* choose Symfony2. In my projects, I choose either the Symfony2 framework
(usually with KnpRadBundle) or Silex, depending no the complexity of the
app. Because they're both built in the same solid core, the learning curve
between the two is basically non-existent.

Happy RAD'ing!

.. _`Good design is no excuse for wasting time`: http://pooteeweet.org/blog/2205
.. _`KnpRadBundle`: http://rad.knplabs.com/
.. _`Laravel`: http://four.laravel.com/
.. _`Silex`: http://silex.sensiolabs.org/
.. _`SonataAdminBundle`: http://sonata-project.org/bundles/admin/master/doc/index.html
.. _`FOSRestBundle`: https://github.com/FriendsOfSymfony/FOSRestBundle