<?php
# vim: set smartindent tabstop=4 shiftwidth=4 set expandtab

class ExifToolBatch {

    private $_exiftool = null;
    private $_defargs = array('-g','-j');
    private $_process=null;
    private $_pipes=null;
    private $_stack=array();
    private $_lastdata=array();
    private $_seq=1;
    private $_socket_get_mode = "fgets";
    private $_debug=1;

    public static function getInstance($path){
        static $inst = null;
        if($inst == null){
            $inst = new ExifToolBatch($path);
        }
        return $inst;
    }

    public function __construct($path){
        if(isset($path)){
            $this->setExifToolPath($path);
        }
        return $this;
    }

    public function __destruct(){
        if(isset($this->_process))
            $this->close();
    }

    public function setExifToolPath($path){
        if(!file_exists($path)){
            throw new Exception('Exiftool path does not exist');
        }
        $this->_exiftool=$path;
	return $this;
    }

    public function start(){
        $env = null;
        $cwd = ".";
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
            2 => array("file", "error-output.txt", "a") // stderr is a file to write to
        );

        if(is_null($this->_exiftool)){
            throw new Exception('Exiftool path was not set');
        }

        $this->_process = proc_open($this->_exiftool.' -stay_open True -@ -', $descriptorspec, $this->_pipes, $cwd, $env);

        stream_set_blocking ($this->_pipes[1],($this->_socket_get_mode=="fgets"?1:0));

        if($this->test()){
            return $this->_process;
        }else{
            throw new Exception('Exiftool did not start');
        }
    }

    public function close(){
        fclose($this->_pipes[0]);
        fclose($this->_pipes[1]);
        proc_terminate($this->_process);
        unset($this->_pipes);
        unset($this->_process);
        return true;
    }

    public function test(){
        fwrite($this->_pipes[0], "-ver\n");
        fwrite($this->_pipes[0], "-execute\n");

        $output = $this->getStreamData("{ready}");
        $output=floatval($output);

        if($output>=9.02){
            return true;
        }else{
            return false;
        }
    }


    private function checkRunning(){
        if(is_null($this->_process)){
            return $this->start();
        }else{
            $status=proc_get_status($this->_process);
            if($status['running']===false){
                $this->close();
                $this->start();
            }
        }
    }

    private function getStreamData($endDelim){
        $endstr=$endDelim."\n";
        $endstr_len=0-strlen($endstr);
        $output=false;
        switch($this->_socket_get_mode){
            case "stream":
            do{
            $str=stream_get_line($this->_pipes[1],2048);
            $last=substr($str,$endstr_len);
            if($last==$endstr) $str = substr($str,0,$endstr_len);
            $output=$output.$str;
            }while($last != $endstr);
                break;
            case "fgets":
                while (($buffer = fgets($this->_pipes[1], 2048)) !== false) {
                    $last=substr($buffer,$endstr_len);
                    if($last == $endstr){ break; }
                    $output=$output.$buffer;
                }
                break;
        }
        return $output;
    }

    private function execute($args){
        $this->checkRunning();

        // $pipes now looks like this:
        // 0 => writeable handle connected to child stdin
        // 1 => readable handle connected to child stdout
        // Any error output will be appended to /tmp/error-output.txt
        foreach($this->_defargs as $arg){
            fwrite($this->_pipes[0], $arg."\n");
        }

        foreach($args as $arg){
            if(!is_string($arg)) continue;
            fwrite($this->_pipes[0], $arg."\n");
        }

        $seq = $this->_seq++;
        fwrite($this->_pipes[0], "-execute".$seq."\n");

        // get all of the output
        $output=false;
        $output = $this->getStreamData("{ready".$seq."}");

        return $output;

    }

    public function decode($data){
        if(is_array($data)){
            $dataArr=array();
            foreach($data as $data2){
                if($data3 = json_decode($data2)){
                    if(is_array($data3)){
                        foreach($data3 as $x){
                            $dataArr[]=$x;
                        }
                    }
                }
            }
            return $dataArr;
        }else{
            if($data=json_decode($data)){
                return $data;
            }else{
                return false;
            }
        }
    }

    public function fetchDecoded(){
        if(!$this->fetch()) return false;
        if($data=json_decode($this->_lastdata)){
            return $data;
        }else{
            return false;
        }
    }

    public function fetch(){
        if(count($this->_stack)){
            $this->_lastdata = $this->execute(array_shift($this->_stack));
            return $this->_lastdata;
        }else{
            unset($this->_lastdata);
            return false;
        }
    }

    public function fetchAllDecoded(){
        if(!$this->fetchAll()) return false;
        $dataArr=array();
        foreach($this->_lastdata as $lastdata){
            if($data = json_decode($lastdata)){
                if(is_array($data)){
                    foreach($data as $x){
                        $dataArr[]=$x;
                    }
                }else{
                    $dataArr[]=$data[0];
                }
            }
        }
        return $dataArr;
    }

    public function fetchAll(){
        $data=array();
        while($args=array_shift($this->_stack)){
            $data[]=$this->execute($args);
        }
        $this->_lastdata=$data;
        return $data;
    }

    public function add($args){
        if(is_array($args)){
            $this->_stack[]=$args;
        }elseif(is_string($args)){
            $this->_stack[]=array($args);
        }else{
            return false;
        }
        return $this;
    }

    public function clear(){
        $this->_stack=array();
        return $this;
    }

}

