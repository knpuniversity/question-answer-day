Symfony2: Organizing your Business Logic into Models
====================================================

From `Audrius`_

  As Symfony is Request/Response rather than MVC framework what is best
  (business/developer ratio) structure to implement model layer into Symfony
  applications.

  Lets say you have a lot of business logic inside your application, or porting
  normal MVC application to Symfony, what is best way (in your opinion) to
  organize structure for applications? All business logic goes into services?
  Fat controllers? Any other solutions?

Answer
------

As Audrius correctly points out, Symfony2 isn't actually an MVC framework,
nor does it want to be. Symfony2 is all about converting a "request" into
a "response". Behind the scenes it uses a simple routing -> controller setup.
Using templates, or creating a rich `service-oriented-architecture`_
is totally optional and up to you. You can even create your own classic
`view layer`_ if you want to.

This means that you have a lot of flexibility on how to organize things.
But in my opinion, the answer is simple: **create a service-oriented architecture
where all your business logic lives in services**. This means having "skinny"
controllers and a "fat" model. There will of course be edge-cases, but this
is almost always the best way to organize things.

.. tip::

    If any of this "skinny controllers" and "fat" models is new to you, check
    out our free `Dependency Injection`_ screencast.

But this isn't a hard rule. Having a perfectly-organized service layer is
something to strive towards, but not something that's always easy - or even good -
in the real world. If you're trying to quickly prototype something, for example,
then creating services is probably not as good as putting the logic directly
in your controller. In fact, you might even argue that logic should live
in the controller unless you're going to unit test it or until you need to
re-use it.

In other words, the goal is to put your logic in services. Balance that with
the real-world requirements of getting things done quickly to compromise
between developing quickly and having clean maintainable code. Adding a lot
of logic to your controller is a perfect example of `Technical Debt`_, which
is a natural part of the development process.

Cheers!

.. _`Audrius`: https://twitter.com/shivas80
.. _`view layer`: http://symfony.com/doc/current/components/http_kernel/introduction.html#the-kernel-view-event
.. _`Technical Debt`: http://en.wikipedia.org/wiki/Technical_debt
.. _service-oriented-architecture`: http://knpuniversity.com/screencast/dependency-injection/container#skinny-controllers-and-service-oriented-architecture
.. _`Dependency Injection`: http://knpuniversity.com/screencast/dependency-injection
