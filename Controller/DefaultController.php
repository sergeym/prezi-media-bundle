<?php

namespace Sergeym\PreziMediaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('SergeymPreziMediaBundle:Default:index.html.twig');
    }
}
