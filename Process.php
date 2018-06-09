<?php
/**
 * Created by PhpStorm.
 * User: wisonlau
 * Date: 2018/6/9
 * Time: 9:37
 */

class Process{
    public $mpid = 0;
    public $max_precess = 5;
    public $works = [];
    public $swoole_table = NULL;
    //public $new_index=0;

    public $memory_size = 1024;


    public function __construct()
    {
        try
        {
            print_r("CPU核数:" . swoole_cpu_num() . ",swoole版本:" . swoole_version() . PHP_EOL);
            $this->swoole_table = new swoole_table($this->memory_size);
            $this->swoole_table->column('index', swoole_table::TYPE_INT); // 用于父子进程间数据交换
            $this->swoole_table->create();

            swoole_set_process_name(sprintf('php-ps:%s', 'master'));
            $this->mpid = posix_getpid();
            $this->run();
            $this->processWait();
        }
        catch (\Exception $e)
        {
            die('ALL ERROR: '.$e->getMessage());
        }
    }

    public function run()
    {
        for ($i = 0; $i < $this->max_precess; $i++)
        {
            $this->CreateProcess();
        }
    }

    public function CreateProcess($index = null)
    {
        if(is_null($index))
        {
            //如果没有指定了索引，新建的子进程，开启计数
            $index = $this->swoole_table->get('index');
            if($index === false)
            {
                $index = 0;
            }
            else
            {
                $index = $index['index'] + 1;
            }
            print_r($index . PHP_EOL);
        }

        $this->swoole_table->set('index', array('index' => $index));
        $process = new swoole_process(function(swoole_process $worker) use ($index)
        {
            swoole_set_process_name(sprintf('php-ps:%s', $index));
            // 业务
            $task = $this->getTask($index);
            foreach ($task as $v){
                $this->test($index, $v);
                // call_user_func_array(array($this, $v['handle']), array($index, $v));
            }
            sleep(2);

        }, false, false);
        $pid = $process->start();

        $this->works[$index] = $pid;
        return $pid;
    }

    private function getTask($index){
        $_return = [];
        foreach ($this->task as $v){
            if($v['hash']==$index){
                $_return[] = $v;
            }
        }
        return $_return;
    }

    //代替从数据库中读取的内容
    public $task = [
        ['uid' => 1, 'uname' => 'bot0', 'hash' => 1, 'handle' => 'test'],
        ['uid' => 2, 'uname' => 'bot1', 'hash' => 2, 'handle' => 'test'],
        ['uid' => 3, 'uname' => 'bot2', 'hash' => 3, 'handle' => 'test'],
        ['uid' => 4, 'uname' => 'bot3', 'hash' => 4, 'handle' => 'test'],
        ['uid' => 5, 'uname' => 'bot4', 'hash' => 2, 'handle' => 'test'],
        ['uid' => 6, 'uname' => 'bot5', 'hash' => 3, 'handle' => 'test'],
        ['uid' => 7, 'uname' => 'bot6', 'hash' => 1, 'handle' => 'test'],
    ];

    function test($index, $task)
    {
        print_r("[" . date('Y-m-d H:i:s') . "]" . 'work-index:' . $index . '处理' . $task['uname'] . '完成' . PHP_EOL);
    }

    public function processWait()
    {
        while(1)
        {
            if(count($this->works))
            {
                $ret = swoole_process::wait();
                // $status = swoole_process::kill($ret['pid'], $signo = 0);
                // if ($status)
                // {
                //     swoole_process::kill($ret['pid'], $signo = SIGTERM);
                // }
                if ($ret)
                {
                    $this->rebootProcess($ret);
                }
            }
            else
            {
                break;
            }
        }
    }

    public function rebootProcess($ret)
    {
        $pid = $ret['pid'];
        $index = array_search($pid, $this->works);
        if($index !== false)
        {
            $index = intval($index);
            $new_pid = $this->CreateProcess($index);
            echo "rebootProcess: {$index}={$new_pid} Done\n";
            return;
        }

        throw new \Exception('rebootProcess Error: no pid');
    }

}

$process = new Process();
