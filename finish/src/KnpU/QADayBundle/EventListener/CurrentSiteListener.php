<?php

namespace KnpU\QADayBundle\EventListener;

use KnpU\QADayBundle\Site\SiteManager;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CurrentSiteListener
{
    private $siteManager;

    private $em;

    private $baseHost;

    public function __construct(SiteManager $siteManager, EntityManager $em, $baseHost)
    {
        $this->siteManager = $siteManager;
        $this->em = $em;
        $this->baseHost = $baseHost;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $host = $request->getHost();
        $baseHost = $this->baseHost;

        // if we're at some totally foreign domain, do nothing
        // this prevents all the other demos int his project from failing :)
        if (strpos($baseHost, $host) === false)  {
            return;
        }

        $subdomain = str_replace('.'.$baseHost, '', $host);

        $site = $this->em
            ->getRepository('QADayBundle:Site')
            ->findOneBy(array('subdomain' => $subdomain))
        ;
        if (!$site) {
            throw new NotFoundHttpException(sprintf(
                'Cannot find site for host "%s", subdomain "%s"',
                $host,
                $subdomain
            ));
        }

        $siteManager = $this->siteManager;
        $siteManager->setCurrentSite($site);
    }
}