<?php
namespace Moschini\PerfToolBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\NotBlank;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

use HarUtils\HarFile;
use HarUtils\HarTime;
use HarUtils\Url;

use Domain\Domain;
use SclWhois\DomainLookup;
use SclSocket\Socket;

class DefaultController extends Controller
{



    /**
     * @Route("/info")
     * @Cache(public="true",maxage="7200")
     * @Template()
     */
    public function infoAction(Request $request)
    {
        $url = $request->get('url');
        $db = $this->get('dbprovider');
        if($url)
        {
            $host = parse_url($url, PHP_URL_HOST);
        
            $rows = $db->getStatsForUrl($url);

        }
        else
        {
            $host = $request->get('host');
            
            $rows = $db->getStatsForHost($host);
        }

        $domain = Domain::getRegisteredDomain($host);

        $timings = $db->getAvgValues($db->sumUp($rows));
        

        $times = $db->getUrlTimes($rows);

        $urls = $db->getUrlsFromTimesList($times);
        
        return array(
            'url' => $url, 
            'urls' => $urls, 
            'domain' => $domain, 
            'host' => $host, 
            'timings' => $timings, 
            'times' => $times);
    }

    /**
     * @Route("/lookup")
     * @Cache(public="true",maxage="86400")
     * @Template()
     */
    public function lookupAction(Request $request)
    {
        $resp = array();

        $host = $request->get('domain');
        
        if(!$host)
            return $resp;
        
        $resp['domain'] = $host;

        $whois = new DomainLookup(new Socket);

        $resp['data'] = $whois->lookup($host);
        return $resp;
    }

    /**
     * @Route("/index")
     * @Cache(public="true",maxage="300")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $db = $this->get('dbprovider');
        $rows = $db->getRecentRequestsList($request->get('site'), $request->get('url'));

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $rows,
            $this->get('request')->query->get('page', 1),
            10
        );

        return array(
            'requests' => $db->getRecentRequests($pagination->getItems()), 
            'pagination' => $pagination
        );
    }
    
    /**
     * @Route("/delete", defaults={"_format"="json"})
     */
    public function deleteAction(Request $request)
    {
        if($request->isXmlHttpRequest())
        {
            $site = $request->get('site');
            $url = $request->get('url');

            $db = $this->get('dbprovider');
            $db->deleteAll($site, $url);
        }
        return new JsonResponse(array('result' => 'success'));
    }

    /**
     * @Route("/send")
     * @Template()
     */
    public function sendAction(Request $request)
    {
        $site = $request->get('site');
        
        $db = $this->get('dbprovider');
        $type = $db->getTypeForSite($site);

        $url = $request->get('url');
        $defaultData = array(
            'type' => 'har', 
            'url' => $url, 
            'site' => $site,
            'agent' => $type ? $type : 'desktop',
            'nb' => 1);

        $form = $this->createFormBuilder($defaultData)
            //->add('type', 'choice', array('choices' => array('har' => 'har', 'loadtime' => 'loadtime')))
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
            ->add('url', 'text', 
                array(
                    'required' => true,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'attr' => array(
                        'placeholder' => 'http://www.google.com',
                        'class' => 'input-xxlarge',
                    ),
            ))
            ->add('agent', 'choice', 
                array(
                    'required' => true,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'label' => 'User-Agent', 
                    'choices' => array(
                        'desktop' => 'Desktop', 
                        'mobile' => 'Mobile',
                    ), 
                    'expanded' => true,
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
                    'nb' => $data['nb'],
                    'account' => 'me',
                    'type' => 'har', //$data['type'],
                    'agent' => $data['agent'],
                );

                // Send to background job
                $celery = new \Celery('localhost', 'guest', 'guest', '/');
                $celery->PostTask('tasks.processtest', array($msg));
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
     * @Cache(public="true",maxage="86400")
     * @Template()
     */
    public function doneAction()
    {
        return array();
    }
    
    /**
     * @Route("/graph")
     * @Cache(public="true",maxage="3600")
     * @Template()
     */
    public function graphAction(Request $request)
    {
        /*$to = new \DateTime();
        $to->modify('+1 day');
        $to->setTime(0, 0);
        $from = clone $to;
        $from->modify('-1 week');
        */
        $db = $this->get('dbprovider');
        $datas = $db->getLoadTimesPerUrl($request->get('site'), $request->get('url'));//, $from, $to);
        
        //$to->modify('-1 day');

        return array(
            'datas' => $datas, 
            );
    }
    
    /**
     * @Route("/time")
     * @Cache(public="true",maxage="86400")
     * @Template()
     */
    public function timeAction(Request $request)
    {
        $tz = new \DateTimeZone('GMT');
        $to = new \DateTime();
        $to->setTimezone($tz);
        $to->setTime(0, 0);

        $from = clone $to;
        $from->modify('-1 week');
        
        $to->modify('+1 day');

        $reqfrom = $request->query->get('from');
        if($reqfrom)
        {
            $from = new \DateTime("@".$reqfrom);
            //$from->setTime(0, 0);
        }
        $reqto = $request->query->get('to');
        if($reqto)
        {
            $to = new \DateTime("@".$reqto);
            $to->modify('+1 day');
            //$to->setTime(0, 0);
        }
        
        $site = $request->get('site');
        $url = $request->get('url');
        
        $db = $this->get('dbprovider');
        $values = $db->getLoadTimeGroupBySites($site, 
            $db->getLoadTimesAndDatePerUrl($site, $url, $from, $to), 
            $from, 
            $to);

        return array(
            'values' => $values, 
            'from' => $from,
            'to' => $to
        );
    }
    
    /**
     * @Route("/harviewer/{id}")
     * @Cache(public="true",maxage="86400")
     * @Template()
     */
    public function harviewerAction(Request $request, $id)
    {
        $db = $this->get('dbprovider');
        $item = $db->getHarItem($id);
        if(!$item) {
            throw new \Exception('Unable to find item with id '.$id);
        }

        $har = HarFile::fromJson($item);
        
        list($previous, $next) = $db->getPreviousNext($item);

        return array(
            'har' => $har,
            'previous' => $db->getObjectId($previous),
            'next' => $db->getObjectId($next),
        );
    }
}
