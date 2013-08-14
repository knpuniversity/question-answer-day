Training: The Hardest Part
==========================

From our friend `John Kary`_:

  When conducting trainings, what is the biggest challenges new Symfony2
  developers faced, and how have you helped them overcome them?

Answer
------

One of the most interesting and rewarding parts of my job is traveling around
and training developers in `Symfony2`_ and `Behat`_. I've worked with developers
from all sort of background - including people new to PHP and people that have
used symfony1 for years.

Usually a training lasts for 2-3 days where we build a real project in Symfony2.
I walk around, ask leading - or misleading :) - questions, then let the trainees
use their own smartness to code, research, and make mistakes.

It's always an awesome experience for everyone, except, for the first half
of the first day. The biggest challenge that new developers face is in the
first 4 hours of being introduced to Symfony2. It's also - paradoxically -
the part where we do the easiest things.

The reason is the sheer number of small things that you learn in those first
4 hours. None of them are hard, but it can be overwhelming:

* Namespaces?
* Composer?
* What is the standard distribution?
* Bundles?
* Remove Acme what? in AppKernel what?
* Why am I editing these random config files? routing_dev.yml? routing.yml?
* What's a route? Why does it live here?
* Why am I creating a `DefaultController` class?
* The app directory? src directory? Bundle directory? Lots of directories!?
* What is this ``::base.html.twig`` file?
* Wait, so ``MyBundle:Default:index`` is different than ``MyBundle:Default:index.html.twig``?

And for the first 4 hours, you learn these all at once. It's also the time
where you see the most "Symfony'isms": things that are perfectly specific
to the Symfony framework. For example, while "routing" is a generic concept
common to all frameworks, the ``MyBundle:Default:index`` controller sytnax
is totally form Symfony.

The good news is that by the end of the first day, this is all ancient history.
By diving in and getting hands-on with the code, we spend the rest of the
training peeling the layers off of Symfony, discovering what's really going
on, how you can take complete control, and more advanced features.

During the first 4 hours, you might be thinking: "I don't know what's going
on, I'm just blindly following these directions". And while I wish this
could be easier - learning something new isn't always simple. But with patience
and perseverance, we always get through it and come running out the other side.

By the end of a few days, you're bored with Symfony, because you've peeled
back all its layers.

And that's really exciting.

.. _`John Kary`: https://twitter.com/johnkary
.. _`Symfony2`: http://knplabs.com/training/symfony2
.. _`Behat`: http://knplabs.com/training/behat