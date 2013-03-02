<?php
namespace Moschini\PerfToolBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use HarUtils\HarFile;

use DbUtils\SitesDb;

class DefaultController extends Controller
{
	/**
     * @Route("/")
     * @Route("/index")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $find = array();
        $site = $request->get('site');
        if($site)
        {
            $find = array('site' => $site);
            return array(
                'files' => SitesDb::getMostRecentFilesFromDB($find)
            );
        }
        else
        {
            $sites = SitesDb::getSites();
            return array('files' => null, 'sites' => $sites);
        }
    }

	/**
     * @Route("/send")
     * @Template()
     */
    public function sendAction(Request $request)
    {
        $defaultData = array('type' => 'har', 'site' => 'mine', 'url' => 'http://');

        $form = $this->createFormBuilder($defaultData)
            ->add('type', 'choice', array('choices' => array('har' => 'har', 'loadtime' => 'loadtime')))
            ->add('site', 'text')
            ->add('url', 'text')
            ->getForm();

        if($request->isMethod('POST'))
        {
            $form->bind($request);
            if($form->isValid())
            {
                $data = $form->getData();
                $msg = array(
                    'url' => $data['url'],
                    'site' => $data['site'],
                    'account' => 'me',
                    'type' => $data['type'],
                );
                $this->get('old_sound_rabbit_mq.upload_picture_producer')->publish(json_encode($msg), 'perftest');
                return $this->redirect($this->generateUrl('moschini_perftool_default_done'));
            }
        }
        return array('form' => $form->createView());
        
    }
	
    /**
     * @Route("/done")
     * @Template()
     */
    public function doneAction()
    {
        return array();
    }
	
	/**
     * @Route("/graph")
     * @Template()
     */
    public function graphAction(Request $request)
	{
		$urls = array();
		$datas = array();
        $sites = array();

        $site = $request->get('site');
        if($site)
        {
            $find = array('site' => $site);
            $files = SitesDb::getAllFilesFromDB($find);

            foreach($files as $har)
            {
                foreach($har->getPages() as $page)
                {
                    $url = $page->getUrl();
                    $url_key = $url->getUid();
                    if(!array_key_exists($url_key, $urls))
                    {
                        $urls[ $url_key ] = $url;
                    }

                    if(!array_key_exists($url_key, $datas)){
                        $datas[ $url_key ] = array();
                    }

                    $datas[ $url_key ][ ] = $page->getLoadTime() / 1000;
                }
            }
        }
        else
        {
            $sites = SitesDb::getSites();
        }
        
		return array(
            'sites' => $sites,
			'datas' => $datas,
			'urls' => $urls,
			);
    }
	
	/**
     * @Route("/time")
     * @Template()
     */
    public function timeAction(Request $request)
	{
        $site = $request->get('site');
        if($site)
        {
            $find = array('site' => $site);
            return array(
                'files' => SitesDb::getAllFilesFromDB($find)
            );
        }
        else
        {
            $sites = SitesDb::getSites();
            return array('files' => null, 'sites' => $sites);
        }

	}
    
	
    /**
     * @Route("/harviewer/{id}")
     * @Template()
     */
	public function harviewerAction($id)
    {
        $db = SitesDb::getDb();
        $item = $db->har->findOne(array('_id' => new \MongoId($id)));
		$har = HarFile::fromJson($item);
        return array('har' => $har);
    }
}
