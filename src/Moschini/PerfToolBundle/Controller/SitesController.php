<?php

namespace Moschini\PerfToolBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
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
        return array(
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
            array('$addToSet' => array('urls' => array('$each', $data['urls']))), 
            array('upsert' => true));
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
        $defaultData = array('interval' => 180);

        $form = $this->createFormBuilder($defaultData)
            ->add('site', 'text')
            ->add('urls', 'textarea')
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
                    return $this->redirect($this->generateUrl('moschini_perftool_sites_done'));
                }
                else
                {
                    echo 'Error while inserting to DB. Please try-again or contact an administrator if the problem persist.';
                }   
            }
        }
        return array('form' => $form->createView());
    }
	
	
}
