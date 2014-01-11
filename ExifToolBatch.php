<?php
# vim: set smartindent tabstop=4 shiftwidth=4 set expandtab

class ExifToolBatch {

    const BUFF_SIZE = 4096;

    private $_exiftool = null;
    private $_defexecargs = array('-use MWG');
    private $_defargs = array('-g','-j');
    private $_process=null;
    private $_pipes=null;
    private $_stack=array();
    private $_lastdata=array();
    private $_seq=0;
    private $_socket_get_mode = "fgets";
    private $_debug=0;

    public static function getInstance($path=null, $args=null){
        static $inst = null;
        if($inst == null){
            $inst = new ExifToolBatch($path, $args);
        }
        return $inst;
    }

    public function __construct($path=null,$args=null){
        if(isset($path)){
            $this->setExifToolPath($path);
        }
        if(isset($args)){
            $this->setDefaultExecArgs($args);
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

    public function getExifToolPath(){
        return $this->_exiftool;
    }

    public function setDefaultExecArgs($args){
        if(!is_array($args)) $args=array($args);
        $this->_defexecargs=$args;
        return $this;
    }

    public function getDefaultExecArgs(){
        return $this->_defexecargs;
    }

    public function setDefaultArgs($args){
        if(!is_array($args)) $args=array($args);
        $this->_defargs=$args;
        return $this;
    }

    public function getDefaultArgs(){
        return $this->_defargs;
    }

    public function setEchoMode($mode=1){
        if(!is_int($mode)) return false;
        $this->execute_cmd(array('-echo'.$mode));
        return true;
    }

    public function sigterm(){
        $this->close();
        exit;
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

        pcntl_signal(SIGTERM,array(&$this,'sigterm'));

        $this->_process = proc_open($this->_exiftool.' '.implode(' ',$this->_defexecargs).' -stay_open True -@ -', $descriptorspec, $this->_pipes, $cwd, $env);

        if(substr($this->_socket_get_mode,0,6)=="stream"){
            stream_set_blocking ($this->_pipes[1],0);
        }else{
            stream_set_blocking ($this->_pipes[1],1);
        }

        if($this->test()){
            return $this->_process;
        }else{
            throw new Exception('Exiftool did not start');
        }
    }

    public function close(){
        fwrite($this->_pipes[0], "-stay_open\nFalse\n");
        fclose($this->_pipes[0]);
        fclose($this->_pipes[1]);
        proc_terminate($this->_process);
        unset($this->_pipes);
        unset($this->_process);
        return true;
    }

    private function run(){
        $this->_seq = $this->_seq + 1;
        $seq=$this->_seq;
        fwrite($this->_pipes[0], "-execute".$seq."\n");
        $output = $this->getStreamData();
        return $output;
    }

    public function test(){
        fwrite($this->_pipes[0], "-ver\n");
        $output = $this->run();
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

    private function getStreamData(){
        $endstr="{ready".$this->_seq."}\n";
        $endstr_len=0-strlen($endstr);
        $output=false;
        $endstr_found=null;
        switch($this->_socket_get_mode){
            case "stream": // fast, high cpu
                do{
                    $str=stream_get_line($this->_pipes[1],self::BUFF_SIZE);
                    $output=$output.$str;
                }while(strpos($output,$endstr)===false);
                $endstr_found=substr($output,$endstr_len);
                $output=substr($output,0,$endstr_len);
                break;
            case "fgets": // fast, low cpu
                do{
                    $str=fgets($this->_pipes[1], self::BUFF_SIZE);
                    $output=$output.$str;
                }while(strpos($str,$endstr)===false);
                $endstr_found=substr($output,$endstr_len);
                $output=substr($output,0,$endstr_len);
                break;
        }
        if($endstr_found!=$endstr){
            throw new Exception('ExifTool out of sequence');
        }
        return $output;
    }

    public function execute($args){
        // merge default args with supplied args
        $argsmerged=array_merge($this->_defargs,$args);
        return $this->execute_cmd($argsmerged);
    }

    public function execute_args($args){
        $this->checkRunning();

        // $pipes now looks like this:
        // 0 => writeable handle connected to child stdin
        // 1 => readable handle connected to child stdout
        // Any error output will be appended to /tmp/error-output.txt
        foreach($args as $arg){
            if(!is_string($arg)) continue;
            fwrite($this->_pipes[0], $arg."\n");
        }

        // get all of the output
        $output = $this->run();

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

