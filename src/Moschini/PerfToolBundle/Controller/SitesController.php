<?php

namespace Moschini\PerfToolBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

use DbUtils\SitesDb;

class SitesController extends Controller
{
    /**
     * @Route("/done")
     * @Cache(public="true",maxage="3600")
     * @Template()
     */
    public function doneAction()
    {
        return array();
    }

	/**
     * @Route("/")
     * @Cache(public="true",maxage="3600")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $sites = SitesDb::getManagedSites();
        $find = array();
        $site = $request->get('site');

        if($site)
        {
            $find = array('site' => $site);
        }

        $configs = SitesDb::getSitesConfig($find);

        return array(
            'current_site' => $site,
            'sites' => $configs
        );
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
     * @Cache(public="true",maxage="86400")
     * @Template()
     */
    public function addAction(Request $request)
    {
        $site = $request->get('site');
        $defaultData = array('interval' => 180, 'site' => $site, 'nb' => 10, 'agent' => 'desktop');

        $form = $this->createFormBuilder($defaultData)
            ->add('site', 'text', 
                array(
                    'required' => true,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'attr' => array(
                        'placeholder' => 'Site name',
                    ),
            ))
            ->add('urls', 'textarea', 
                array(
                    'required' => true,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'attr' => array(
                        'placeholder' => 'One urls per line',
                        'rows' => 5,
                        'class' => 'input-xxlarge',
                       ),
            ))
            ->add('agent', 'choice', 
                array(
                    'required' => true,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'choices' => array(
                        'desktop' => 'Desktop', 
                        'mobile' => 'Mobile'), 
                    'expanded' => true,
                    'label' => 'User-Agent',
            ))
            ->add('nb', 'integer', 
                array(
                    'required' => true,
                    'label' => 'Number of requests',
                    'attr' => array(
                        'class' => 'input-mini',
                    ),
                    'constraints' => array(
                        new Range(array('min' => 1, 'max' => 20)),
                    ),
            ))
            ->add('interval', 'choice', 
                array(
                    'required' => true,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'choices' => array(
                        5    => '5 min', 
                        10   => '10 min', 
                        30   => '30 min', 
                        60   => '1 h', 
                        180  => '3 h', 
                        360  => '6 h', 
                        720  => '12 h', 
                        1440 => '24 h',
                    ),
                    'attr' => array(
                        'class' => 'input-small',
                    )
            ))
            ->getForm();

        if($request->isMethod('POST'))
        {
            $form->bind($request);
            if($form->isValid())
            {
                $data = $form->getData();
                $data['urls'] = $this->getUrls($data['urls']);
                
                if(SitesDb::insertToDb($data))
                {
                    return $this->redirect($this->generateUrl('moschini_perftool_sites_index', array('site' => $data['site'])));
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
     * @Cache(public="true",maxage="180")
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
            'nb' => array_key_exists('nb', $config) ? $config['nb'] : 1,
            'agent' => array_key_exists('agent', $config) ? $config['agent'] : 'desktop',
        );

        $form = $this->createFormBuilder($defaultData)
            ->add('site', 'text')
            ->add('urls', 'textarea', 
                array(
                    'required' => true,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'attr' => array(
                        'rows' => 10,
                        'class' => 'input-xxlarge',
                    ),
            ))
            ->add('agent', 'choice', 
                array(
                    'required' => true,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'choices' => array(
                        'desktop' => 'Desktop', 
                        'mobile' => 'Mobile'), 
                    'expanded' => true,
                    'label' => 'User-Agent',
            ))
            ->add('nb', 'integer', 
                array(
                    'required' => true,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'label' => 'Number of requests',
                    'attr' => array(
                        'class' => 'input-mini',
                    ),
                    'constraints' => array(
                        new Range(array('min' => 1, 'max' => 20)),
                    ),
            ))
            ->add('interval', 'choice', 
                array(
                    'required' => true,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'choices' => array(
                        5    => '5 min', 
                        10   => '10 min', 
                        30   => '30 min', 
                        60   => '1 h', 
                        180  => '3 h', 
                        360  => '6 h', 
                        720  => '12 h', 
                        1440 => '24 h',
                    ),
                    'attr' => array(
                        'class' => 'input-small',
                    )
            ))
            ->getForm();

        if($request->isMethod('POST'))
        {
            $form->bind($request);
            if($form->isValid())
            {
                $data = $form->getData();
                $data['urls'] = $this->getUrls($data['urls']);
                
                if(SitesDb::updateToDb($id, $data))
                {
                    return $this->redirect($this->generateUrl('moschini_perftool_sites_index', array('site' => $site)));
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
     * @Cache(public="true",maxage="180")
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
            'current_site' => $site,
            'route' => $route['_route']
        );
    }
	
	/**
     * @Route("/sites")
     * @Cache(public="true",maxage="180")
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
     * @Cache(public="true",maxage="300")
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
