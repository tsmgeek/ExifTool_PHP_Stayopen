<?php

$gmc = new GearmanClient();

// add the default job server
$gmc->addServer("127.0.0.1",4730);
$gmc->setCompleteCallback("complete");

$loops=100;
for($i=1;$i<=$loops;$i++){
    $job=array();
    $job[]="-all:all";
    $job[]="/home/user/test".i.".jpg";
    $res=$gmc->addTask("dev-ExifTool", serialize($job), null, "iptc".$i);
}
$results=array();

if (! $gmc->runTasks())
{
    echo "ERROR " . $gmc->error() . "\n";
    exit;
}

foreach($results as $id=>$result){
    print $id."\n";
}

function complete($task, $results){
    global $results;
    print "-";
   $results[$task->unique()] = array("handle"=>$task->jobHandle(), "data"=>$task->data());
}
