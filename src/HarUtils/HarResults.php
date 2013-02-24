<?php

namespace HarUtils;

use HarUtils\HarFile;

class HarResults
{
    private $files = array();

    public function __construct($cursor)
    {
        foreach($cursor as $document)
        {
            $this->files[ $document['_id']->{'$id'} ] = HarFile::fromJson($document);
        }
    }
    
    public function getFiles()
    {
        return $this->files;
    }
}
