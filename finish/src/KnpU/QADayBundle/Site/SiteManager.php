<?php

namespace KnpU\QADayBundle\Site;

class SiteManager
{
    private $currentSite;

    public function getCurrentSite()
    {
        return $this->currentSite;
    }

    public function setCurrentSite($currentSite)
    {
        $this->currentSite = $currentSite;
    }
}