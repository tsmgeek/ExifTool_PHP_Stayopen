<?php

include_once('ExifToolBatch.php');

// Create our worker object.
$gmworker= new GearmanWorker();

// Add default server (localhost).
$gmworker->addServer("127.0.0.1",4730);

// Add function to dispatch selected photos to a destination
$gmworker->addFunction("dev-ExifTool", "exiftool");



print "Waiting for iptc job...\n";
while($gmworker->work())
{
  if ($gmworker->returnCode() != GEARMAN_SUCCESS)
  {
    echo "return_code: " . $gmworker->returnCode() . "\n";
    break;
  }
}

function exiftool($job){
    $data = unserialize($job->workload());
    $exif = ExifToolBatch::getInstance('/usr/local/exif/exiftool');
    print "-";
    $exif->add($data);
    $x=$exif->fetch();
    return $x;
}
