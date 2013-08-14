Symfony2 Security, Firewalls and Dinosaurs
==========================================

From Gerard Araujo:

  What is a typical/ideal bundle and firewall structure for symfony 2 for a
  project with the following basic requirements:

  - frontend [ public ]
  - frontend [ for logged in ]
  - backend [ for admin ]

  ... and a few entities that are owned by users like books, media, category...

  1. assuming i use fosuserbundle, should i have at least 2 bundles ( 1 for fos extension ) ? more than 2? or only 1?
  what advantage/disadvantage to i get with each option?

  2. is one firewall sufficient? what if i need different login 
  routes?

  3. how many dinosaurs does it take to replace a lightbulb?

Answer
------

Hi Gerard! Uh oh, a security question!

  ... ryan runs away...

Actually, this should be pretty painless. The security component in Symfony2
sometimes suffers from being so flexible that it's not clear how to configure
it. Let's try to clarify a bit.

1) FOSUserBundle and Number of Bundles in my Project
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

This has nothing to do with security, but is a common question: how many
bundles should I have and how do I know when I need to create a new bundle?
In this case, Gerard is using `FOSUserBundle`_ and is wondering how to organize
the bundles in his project. In your project, you *will* need a bundle for
"User" functionality like your User entity, templates that override FOSUserBundle
templates, etc etc.

As Gerard is eluding to, when you want to override pieces of a vendor bundle,
there are typically two strategies:

1) Placing files in the ``app`` directory in a specific organization to
override some files from a vendor bundle (http://symfony.com/doc/current/book/templating.html#overriding-bundle-templates)

2) Using `bundle inheritance`_.

In the second strategy, you would create a ``UserBundle`` (or ``AcmeUserBundle``
depending on your "vendor" namespace), set its parent to ``FOSUserBundle``,
then begin overriding things.

But let's step back for a second. On a philosophical level, how many bundles
should our project have? 1? 5? 50? The answer - like with anything - is up
to you. However, don't fool yourself by thinking that you can separate your
features into totally standalone, decoupled bundles. In reality, your bundles
will be totally coupled to each other and often times it won't be clear exactly
which bundle some piece of functionality should live in. And that's ok! We're
building one application with one codebase: not an open-source library.

The point is this: don't create new bundles each time you have a new idea.
Try to keep your total number of bundles low, and create a new bundle only
wen you feel that things are getting crowded.

In our example, I *would* create a ``UserBundle`` in my project, because
I personally really like the "bundle inheritance" strategy for overriding
parts of a vendor bundle. And because I did this, I would put *all* my user
stuff in here (I wouldn't create yet another bundle for user stuff that doesn't
relate to FOSUserBundle).

Beyond that, it's up to you. You might choose to create only one other bundle
and put everything into it or create several other bundles. Just don't go
overboard.... trust me!

2) Number of Firewalls
~~~~~~~~~~~~~~~~~~~~~~

One firewall is enough.

I can say this almost regardless of what your project looks like. We talk
a lot about firewalls and organization in `Starting in Symfony2 Episode 2`_
and while there are good use-cases for multiple firewalls, they're not very
common. Legitimate reasons include:

1) You only use security for one part of your site, that part of your site
lives under a specific URL pattern (e.g. ``/admin``), and you're very very
worried about the small performance hit that loading the security system
will cause on every page outside of this section.

2) You have an API that authenticates in a completely different way than
your frontend, user data is loaded from a different source, and the API is
also only accessible under a very specific URL pattern (e.g. ``/api``).

Having multiple firewalls can cause a lot of extra work and confusion. If
you have a "frontend" and an "admin" section, my advice is to have only one
firewall, load users all from the same source (e.g. from the same database
table), then control access to different users and areas of your sites via
roles and access controls. This will make you much happier :).

3) How many dinosaurs does it take to replace a lightbulb?
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

I watched Jurassic Park last night to research this question, but no light
bulbs! But I can say that it only takes one dinosaur to open a door... and
then bad things happen.

Cheers!

.. _`FOSUserBundle`: https://github.com/FriendsOfSymfony/FOSUserBundle
.. _`bundle inheritance`: http://symfony.com/doc/current/cookbook/bundles/inheritance.html
.. _`Starting in Symfony2 Episode 2`: http://knpuniversity.com/screencast/starting-in-symfony2-episode-2-2-1