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
        return array(
            'files' => $this->getFilesFromDb($request), 
        );
    }

	/**
     * @Route("/send")
     * @Template()
     */
    public function sendAction(Request $request)
    {
        $site = $request->get('site');
        $url = $request->get('url');
        $defaultData = array('type' => 'har', 'url' => $url, 'site' => $site);

        $form = $this->createFormBuilder($defaultData)
            //->add('type', 'choice', array('choices' => array('har' => 'har', 'loadtime' => 'loadtime')))
            ->add('site', 'text', array(
                'attr' => array(
                    'placeholder' => 'Site name',
                )
            ))
            ->add('url', 'text', array(
                'attr' => array(
                    'placeholder' => 'http://www.google.com',
                )
            ))
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
                    'type' => 'har', //$data['type'],
                );
                $this->get('old_sound_rabbit_mq.upload_picture_producer')->publish(json_encode($msg), 'perftest');
                // If no site has been defined, use the one used for this request 
                if(!$site){
                    $site = $data['site'];
                }
                return $this->redirect($this->generateUrl('moschini_perftool_default_done', array('site' => $site)));
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

        $files = $this->getFilesFromDb($request);

        if($files)
        {
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
        
		return array(
			'datas' => $datas,
			'urls' => $urls,
			);
    }

    private function getFilesFromDb($request)
    {
        return SitesDb::getFilesFromFilter($request->get('site'), $request->get('url'));
    }

	/**
     * @Route("/time")
     * @Template()
     */
    public function timeAction(Request $request)
	{

        $files = $this->getFilesFromDb($request);
        $values = array();
        $urls = array();

        foreach((array)$files as $har)
        {
            foreach($har->getPages() as $id => $page)
            {
                $url = $page->getUrl();
                $url_key = $url->getUid();

                if(!array_key_exists($url_key, $values))
                {
                    $values[ $url_key ] = array();
                }
                
                $values[$url_key][] = $page->getLoadTimeAndDate();
                
                if(!array_key_exists($url_key, $urls))
                {
                    $urls[ $url_key ] = $url;
                }

            }
        }

        return array(
            'files' => $files, 
            'values' => $values, 
            'urls' => $urls, 
        );
	}

    private function addOrderByAndDate($query, $field, $value, $sort = 1)
    {
        $operator = $sort == 1 ? '$gt' : '$lt';
        $query[$field] = array($operator => $value);
	    return array('query' => $query, 'orderby' => array($field => $sort));
    }

    private function getPreviousNext($item)
    {
        $db = SitesDb::getDb();
        $find = $this->getRelatedFinder($item);
        $date = $item['log']['pages'][0]['startedDateTime'];
        $findNext = $this->addOrderByAndDate($find, 'log.pages.startedDateTime', $date, 1);
        $findPrevious = $this->addOrderByAndDate($find, 'log.pages.startedDateTime', $date, -1);
        $next = $db->har->findOne($findNext, array('_id' => 1));
        $previous = $db->har->findOne($findPrevious, array('_id' => 1));
        return array($previous, $next);
    }

    private function getRelatedFinder($item)
    {
        $site = $item['site'];
        $url = $item['log']['entries'][0]['request']['url'];
        return array(
            '_id' => array('$ne' => $item['_id']),
            'site' => $site, 
            'log.entries.request.url' => $url
        );
    }

    private function getObjectId($item)
    {
        return $item ? $item['_id'] : null;
    }

    /**
     * @Route("/harviewer/{id}")
     * @Template()
     */
	public function harviewerAction(Request $request, $id)
    {
        $db = SitesDb::getDb();
        $mongoid = new \MongoId($id);
        $item = $db->har->findOne(array('_id' => $mongoid));
		$har = HarFile::fromJson($item);
        
        list($previous, $next) = $this->getPreviousNext($item);

        return array(
            'har' => $har,
            'previous' => $this->getObjectId($previous),
            'next' => $this->getObjectId($next),
        );
    }
}
