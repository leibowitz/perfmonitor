<?php
namespace Moschini\PerfToolBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use HarUtils\HarOutput;
use HarUtils\HarFile;
use HarUtils\HarResults;

class DefaultController extends Controller
{
	/**
     * @Route("/")
     * @Route("/index")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $m = new \MongoClient();
        $db = $m->selectDB("perfmonitor");  
        $cursor = $db->har->find();
        $result = new HarResults($cursor);
        $files = $result->getFiles();
        return array(
            'files' => $files,
            'cursor' => $cursor
        );
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
    public function graphAction()
	{
		$urls = array();
		$datas = array();

		$files = $this->getHarFiles();

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
		return array(
			'datas' => $datas,
			'urls' => $urls,
			);
    }
	
	/**
     * @Route("/time")
     * @Template()
     */
    public function timeAction()
	{
		return array(
			'files' => $this->getHarFiles(),
			);
	}

	private function getHarFiles($glob = '../harfiles/inline-scripts-block.har')
	{
		$files = glob($glob);
		$harfiles = array();
		foreach($files as $file)
		{
			$harfiles[$file] = HarFile::fromFile($file);
		}
		return $harfiles;
	}
	
    /**
     * @Route("/harviewer/{id}")
     * @Template()
     */
	public function harviewerAction($id)
    {
        $m = new \MongoClient();
        $db = $m->selectDB("perfmonitor");  
        $item = $db->har->findOne(array('_id' => new \MongoId($id)));
		$har = HarFile::fromJson($item);
        return array('har' => $har);
    }
}
