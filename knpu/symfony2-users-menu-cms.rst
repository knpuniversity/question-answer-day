Complex Symfony2 Examples: Users, Menus, CMS Features
=====================================================

From pieter lelaona:

  It's been very difficult to find examples of applications and explanations
  actually implement symfony2 framework. e.g.
  
  1. how to create a complete multi-user management with an ACL that retrieves data from a database and integrated with the menus link, filter data, and dynamic multi-role and permission
  2. best project skeleton in symfony2 framework.
  3. create a dynamic system such as the theme cms wordpress, drupal, joomla, etc

  This is a small part of what many people, especially in my country (Indonesia)
  want to learn more about the Symfony2 framework.
  what do you think??

Answer
------

Hi Pieter! As you know, Symfony2 isn't a CMS but contains all the tools needed
to create any system of any complexity that you want. However, for complex
systems like you're describing, there are ultimately many pieces that need
to be integrated to get this all working.

This is a huge topic, but let's go through your questions and clarify the
best way to approach each.

1) Multi-user system with ACLs, Menus and Filtering
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

This is *still* a huge topic, so let's break it down even further:

a) `Multi-User systems`_
b) `ACL's`_
c) `Menus`_
d) `Filtering`_

Multi-User Systems
..................

Creating multi-user systems that load user and permission information from
the database is easy in Symfony2. Depending on your preference, you will
probably either use the popular `FOSUserBundle`_ or implement this yourself
by following our `How to load Security Users from the Database`_ cookbook entry.

In either case, creating a system with "groups" and "permissions" is very possible,
where a user belongs to many groups and each group has a sub-set of permissions.
In Symfony's point-of-view, each user ultimately has an array of "roles",
which are returned by your User object's ``getRoles`` function. You can use
whatever logic you want to return these, including referencing "groups" and
"permissions" database relationships.

In fact, `Groups Functionality`_ is available in FOSUserBundle out-of-the-box.
This works simply because their base ``User`` object calculates its roles
by aggregating all of the roles (or permissions) across all of the groups::

    public function getRoles()
    {
        $roles = $this->roles;

        foreach ($this->getGroups() as $group) {
            $roles = array_merge($roles, $group->getRoles());
        }

        // we need to make sure to have at least one role
        $roles[] = static::ROLE_DEFAULT;

        return array_unique($roles);
    }

You can do the same thing - or whatever complex logic you want - to determine
the roles that a user should have.

.. _symfony2-acl-voters:

ACL's
.....

This is a very common question, and my answer might be surprising.

Symfony2 has built-in `ACL functionality`_, which I *never* use. I'm sure
it has its use-cases, but each time that I talk to someone that wants to
use Symfony's ACL's, what they really need is a *voter*.

What's a voter? I'm glad you asked! First, let's look at one way to enforce
security from within a controller::

    use Symfony\Component\Security\Core\Exception\AccessDeniedException;
    // ...

    public function indexAction()
    {
        $securityContext = $this->container->get('security.context');
        if (!$securityContext->isGranted('ROLE_USER')) {
            throw new AccessDeniedException('Get outta here!');
        }
    }

On the surface, ``isGranted`` simply checks to see if the current user has
this role and returns ``true`` or ``false``. But behind the scenes, Symfony
passes ``ROLE_USER`` (called an "attribute") to a number of "voters" and asks
each to "vote" on whether or not the current user should be "granted" ``ROLE_USER``.

And while it's technically possible for two voters to vote on a single attribute
and disagree with each other, life is much simpler in reality. Symfony2 comes
with 3 voters by default:

1) `RoleVoter`_ Votes only if the attribute starts with ``ROLE_`` and checks
to see if the current user has this exact attribute as a role.

2) `RoleHierarchyVoter`_ Votes only if the attribute starts with ``ROLE_``
and checks to see if the user has this role by using the `role hierarchy`_.

3) `AuthenticatedVoter`_ Votes only if the attribute is ``IS_AUTHENTICATED_FULLY``,
``IS_AUTHENTICATED_REMEMBERED`` or ``IS_AUTHENTICATED_ANONYMOUSLY``.

So what happens if we invent a new type of attribute that none of these voters
"votes" on?

.. code-block:: php

    use Symfony\Component\Security\Core\Exception\AccessDeniedException;
    // ...

    public function indexAction()
    {
        $securityContext = $this->container->get('security.context');
        if (!$securityContext->isGranted('CONTENT_EDIT')) {
            throw new AccessDeniedException('Get outta here!');
        }
    }

In this case, none of the existing voters will vote on ``CONTENT_EDIT``.
You won't get an error: ``isGranted`` will silently return ``false``
(by default). This is significant - as we'll see in a moment - because we
can create our own voters that respond on these new attributes.

One other commonly-unknown property of ``isGranted`` is that there's a second
argument, which is any type of "object"::

    use Symfony\Component\Security\Core\Exception\AccessDeniedException;
    // ...

    public function showAction($slug)
    {
        $post = // query for a Post object using the $slug
    
        $securityContext = $this->container->get('security.context');
        if (!$securityContext->isGranted('CONTENT_EDIT', $post)) {
            throw new AccessDeniedException('Get outta here!');
        }
    }

When you do this, each "voter" is passed the object. This is very important
because it means that your custom voter can make its access decision based
off of a specific piece of data. This is typically what you think of when
you talk about ACL: the ability to say that "this user" has access to "edit"
some "object". In Symfony2, you can leverage a custom voter to use
whatever complex business logic you have to determine this.

This is a somewhat shortened version of this topic, but there is a cookbook
article on `creating voters`_. However, you'll do several things differently
in your implementation:

* Invent your own attributes - like ``CONTENT_EDIT`` and ``CONTENT_DELETE``
  and make your voter only respond to those.

* Use the ``$object`` argument passed to your ``vote`` function. You may
  then need to determine what type of object it is (e.g. is this a blog post?
  A user object?) and use any business rules you have (querying some database
  relationships) to determine if access should be granted.

* You will not need to change the "Access Decision Strategy".

I hope this at least gives you some direction on using ACL's without ACL's
in Symfony2! The big disadvantage to this method is performance. But since
the solution is so much more natural than ACL's, you should worry about this
later when it's an issue. You can always cache the decisions you're making,
which is very similar to what true ACL's do in the database.

Menus
.....

If you're building complex menus in Symfony2, then you should be using
`KnpMenuBundle`_.

This bundle allows you to build your menus inside a PHP class. This is really
important because it means that you can do whatever you want when determining
which menu items to show or not show for a user.

Let's start with example that's directly from the `KnpMenuBundle Documentation`_::

    // src/Acme/DemoBundle/Menu/Builder.php
    namespace Acme\DemoBundle\Menu;

    use Knp\Menu\FactoryInterface;
    use Symfony\Component\DependencyInjection\ContainerAware;

    class Builder extends ContainerAware
    {
        public function mainMenu(FactoryInterface $factory, array $options)
        {
            $menu = $factory->createItem('root');

            $menu->addChild('Home', array('route' => 'homepage'));
            $menu->addChild('About Me', array(
                'route' => 'page_show',
                'routeParameters' => array('id' => 42)
            ));
            // ... add more children

            return $menu;
        }
    }

To conditionally show the ``About Me`` link, we can wrap it in a call to
the ``isGranted`` function::

    $securityContext = $this->container->get('security.context');
    if (!$securityContext->isGranted('ROLE_ADMIN')) {
        $menu->addChild('About Me', array(
            'route' => 'page_show',
            'routeParameters' => array('id' => 42)
        ));
    }

Remember also that you can use your own custom attributes here that hook
up to your own custom voters. There are certainly more complex things beyond
this, but it will always mean using your voters to determine which entries
should be shown.

Filtering
.........

The last piece of all of this is how we filter data based on the user's permissions.
Unfortunately, this works much differently than voters where you start with
an object and then determine if the user has some sort of permissions to
operate on that object.

One way or another, the solution is one that comes down to writing good repository
methods that filter your data properly. For example, suppose that you have
a ``Post`` entity with a ManyToMany relationship to ``User`` that stores
all of the users that have access to edit this blog post::

    // src/KnpU/QADayBundle/Entity/Post.php
    // ...

    /**
     * @ORM\ManyToMany(targetEntity="User")
     */
    protected $admins;

In this case, a custom repository method should be added to ``PostRepository``
to fetch all of the blog posts that this user can edit::

    // src/KnpU/QADayBundle/Entity/PostRepository.php
    // ...

    public function findAllEditableByUser(User $user)
    {
        // query for all Post objects that have a Post.admins join to this User
    }

This can be used from within your controller and a related (more efficient)
version could also be used inside your custom voter to determine if a user
has access to edit one specific blog post. These two repository functions
can share most of their logic to avoid any duplication.

In other words, there's no magic to do all of this, but the solution is quite
straightforward. By leveraging well-built repository methods, we can re-use
that logic in both our custom voters (when determining if a user has access
to do something with an object) and in a controller (to get a list of all
the items a user has access to).

2) Best Project Skeleton for Symfony2
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Symfony2 uses "distributions", which are like pre-started projects using
the Symfony2 framework. In theory, there could be a lot of these, though in
practice, there aren't very many that I'm aware of. Your best option is to
start with the Symfony Standard Edition, which can be `downloaded at Symfony.com`_.

If you've started a few projects with Symfony, and they always look the same,
then you might even create your own distribution. A distribution is nothing
more than a Symfony2 "project" at some state. In other words, if you start
with the Symfony2 Standard Distribution, delete the AcmeDemoBundle, then
install and configure a few bundles that you like, then you've just created
your very own project skeleton. This is a great option for people that start
a lot of Symfony2 projects.

3) Dynamic systems and themes like a CMS
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

This is also a huge topic, but we can at least link to various resources
related to this.

On the "CMS" side of things (particularly content storage), take a look at
the `Symfony CMF`_ project. This is not meant to be a CMS - if you need something
like a CMS, I recommend using an actual CMS, like Drupal. Instead, it's all
about standardizing how content is stored.

If you're looking for "theming" functionality, that's also very possible in
Symfony2 due to its flexibility. One great bundle for this - which may work
for you or at least serve as an example - is `LiipThemeBundle`_.

That's a rushed explanation of a *huge* question, but hopefully it gives you
some things to look into!

Cheers!

.. _`FOSUserBundle`: https://github.com/FriendsOfSymfony/FOSUserBundle
.. _`How to load Security Users from the Database`: http://symfony.com/doc/current/cookbook/security/entity_provider.html
.. _`Groups Functionality`: https://github.com/FriendsOfSymfony/FOSUserBundle/blob/master/Resources/doc/groups.rst
.. _`ACL functionality`: http://symfony.com/doc/current/cookbook/security/acl.html
.. _`RoleVoter`: https://github.com/symfony/symfony/blob/2.2/src/Symfony/Component/Security/Core/Authorization/Voter/RoleVoter.php
.. _`RoleHierarchyVoter`: https://github.com/symfony/symfony/blob/2.2/src/Symfony/Component/Security/Core/Authorization/Voter/RoleHierarchyVoter.php
.. _`AuthenticatedVoter`: https://github.com/symfony/symfony/blob/2.2/src/Symfony/Component/Security/Core/Authorization/Voter/AuthenticatedVoter.php
.. _`role hierarchy`: http://symfony.com/doc/current/book/security.html#hierarchical-roles
.. _`creating voters`: http://symfony.com/doc/current/cookbook/security/voters.html
.. _`KnpMenuBundle`: https://github.com/KnpLabs/KnpMenuBundle
.. _`KnpMenuBundle Documentation`: https://github.com/KnpLabs/KnpMenuBundle/blob/master/Resources/doc/index.rst#create-your-first-menu
.. _`downloaded at Symfony.com`: http://symfony.com/download
.. _`Symfony CMF`: http://cmf.symfony.com/
.. _`LiipThemeBundle`: https://github.com/liip/LiipThemeBundle
