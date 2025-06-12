<?php

namespace SiteZen\Telemetry\Bootstrap;

class Data
{
    var $data;
    public function __construct($data, $processTime)
    {
        $returnArray = $data;
        $returnArray['_request'] = $processTime;
        $this->data = $returnArray;
    }
    public function __toString()
    {
        return json_encode($this->data);
    }

    public function output() {
        header('Content-Type: application/json');
        echo $this;
    }

}
