<?php

namespace Moschini\PerfToolBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use DbUtils\SitesDb;

class SitesController extends Controller
{
    /**
     * @Route("/done")
     * @Template()
     */
    public function doneAction()
    {
        return array();
    }

	/**
     * @Route("/")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $sites = SitesDb::getManagedSites();
        $find = array();
        $site = $request->get('site');

        if($site && in_array($site, $sites))
        {
            $find = array('site' => $site);
        }
        else
        {
            $site = null;
        }

        $configs = SitesDb::getSitesConfig($find);

        return array(
            'current_site' => $site,
            'sites' => $configs
        );
    }

    private function insertToDb($data)
    {
        $m = new \MongoClient();
        $db = $m->selectDB("perfmonitor");  
        $key = array(
            'site' => $data['site'],
            'interval' => $data['interval'],
        );
        
        return $db->sites->update($key, 
            array('$addToSet' => array('urls' => array('$each' => $data['urls']))), 
            array('upsert' => true));
    }
    
    private function updateToDb($id, $data)
    {
        $m = new \MongoClient();
        $db = $m->selectDB("perfmonitor");  
        $key = array(
            '_id' => $id
        );
        
        return $db->sites->update($key, array('$set' => $data)); 
    }


    private function getUrls($urls)
    {
        $urls = str_replace("\r\n", "\n", $urls);
        $urls = str_replace("\r", "\n", $urls);
        return array_values(
            array_filter(
                array_map('trim', 
                    explode("\n", $urls)
                )
            )
        );
    }

    /**
     * @Route("/new")
     * @Template()
     */
    public function addAction(Request $request)
    {
        $site = $request->get('site');
        $defaultData = array('interval' => 180, 'site' => $site);

        $form = $this->createFormBuilder($defaultData)
            ->add('site', 'text', array(
                'attr' => array(
                    'placeholder' => 'Site name',
                )
            ))
            ->add('urls', 'textarea', array(
                'attr' => array(
                    'placeholder' => 'One urls per line',
                    'rows' => 5
                   )
            ))
            ->add('interval', 'choice', 
                array('choices' => array(
                    5 => '5 min', 
                    10 => '10 min', 
                    30 => '30 min', 
                    60 => '1 hour', 
                    180 => '3 hours', 
                    360 => '6 hours', 
                    720 => '12 hours', 
                    1440 => '24 hours')))
            ->getForm();

        if($request->isMethod('POST'))
        {
            $form->bind($request);
            if($form->isValid())
            {
                $data = $form->getData();
                $data['urls'] = $this->getUrls($data['urls']);
                
                if($this->insertToDb($data))
                {
                    return $this->redirect($this->generateUrl('moschini_perftool_sites_done', array('site' => $site)));
                }
                else
                {
                    echo 'Error while inserting to DB. Please try-again or contact an administrator if the problem persist.';
                }   
            }
        }
        return array('form' => $form->createView());
    }
	
    /**
     * @Route("/edit")
     * @Template()
     */
    public function editAction(Request $request)
    {
        $site = $request->get('site');
        $id = new \MongoId($request->get('id'));

        $config = SitesDb::getSitesConfig(array('_id' => $id));
        
        $defaultData = array(
            'interval' => $config['interval'], 
            'site' => $config['site'],
            'urls' => implode("\n", $config['urls']),
        );

        $form = $this->createFormBuilder($defaultData)
            ->add('site', 'text')
            ->add('urls', 'textarea', array(
                'attr' => array(
                    'rows' => 10
                    )
            ))
            ->add('interval', 'choice', 
                array('choices' => array(
                    5 => '5 min', 
                    10 => '10 min', 
                    30 => '30 min', 
                    60 => '1 hour', 
                    180 => '3 hours', 
                    360 => '6 hours', 
                    720 => '12 hours', 
                    1440 => '24 hours')))
            ->getForm();

        if($request->isMethod('POST'))
        {
            $form->bind($request);
            if($form->isValid())
            {
                $data = $form->getData();
                $data['urls'] = $this->getUrls($data['urls']);
                
                if($this->updateToDb($id, $data))
                {
                    return $this->redirect($this->generateUrl('moschini_perftool_sites_done', array('site' => $site)));
                }
                else
                {
                    echo 'Error while updating record. Please try-again or contact an administrator if the problem persist.';
                }   
            }
        }
        return array('form' => $form->createView());
    }
	
    /**
     * @Route("/managedsites")
     * @Route("/managedsites/{site}")
     * @Template() 
     */
    public function managedsitesAction(Request $request)
    {
        $context = new RequestContext();
        $context->fromRequest(Request::createFromGlobals());
        $route = $this->get('router')->match($context->getPathInfo());

        $site = $request->get('site');

        $sites = SitesDb::getManagedSites();

        return array(
            'sites' => $sites, 
            'current_site' => in_array($site, $sites) ? $site : null,
            'route' => $route['_route']
        );
    }
	
	/**
     * @Route("/sites")
     * @Route("/sites/{site}")
     * @Template()
     */
    public function sitesAction(Request $request)
    {
        $context = new RequestContext();
        $context->fromRequest(Request::createFromGlobals());
        $route = $this->get('router')->match($context->getPathInfo());

        $site = $request->get('site');

        return array(
            'sites' => SitesDb::getSitesAndUrls(), 
            'current_site' => $site,
            'route' => $route['_route']
        );
    }

	/**
     * @Route("/js")
     * @Template()
     */
    public function jsAction(Request $request)
    {
        $context = new RequestContext();
        $context->fromRequest(Request::createFromGlobals());
        $route = $this->get('router')->match($context->getPathInfo());
        $site = $request->get('site');
        
        return array(
            'current_site' => $site,
            'route' => $route['_route']
        );
    }

}
