<?php
/**
 * Created by PhpStorm.
 * User: Jenner
 * Date: 2015/11/2
 * Time: 17:45
 */

namespace Jenner\SimpleFork;


class FixedPool extends AbstractPool
{

    /**
     * @var callable|Runnable sub process callback
     */
    protected $runnable;

    /**
     * @var int max process count
     */
    protected $max;

    /**
     * @param callable|Runnable $callback
     * @param int $max
     */
    public function __construct($callback, $max = 10)
    {
        if (!is_callable($callback) && !($callback instanceof Runnable)) {
            $message = "callback must be a callback function or a object of Runnalbe";
            throw new \InvalidArgumentException($message);
        }

        $this->runnable = $callback;
        $this->max = $max;
    }

    /**
     * start the pool
     */
    public function start()
    {
        $alive_count = $this->aliveCount();
        // create sub process and run
        if ($alive_count < $this->max) {
            $need = $this->max - $alive_count;
            for ($i = 0; $i < $need; $i++) {
                $process = new Process($this->runnable);
                $process->start();
                $this->processes[$process->getPid()] = $process;
            }
        }
    }

    /**
     * start the same number processes and kill the old sub process
     * just like nginx -s reload
     * this method will block until all the old process exit;
     *
     * @param bool $block
     */
    public function reload($block = true)
    {
        $old_process = $this->processes;
        for ($i = 0; $i < $this->max; $i++) {
            $process = new Process($this->runnable);
            $process->start();
            $this->processes[$process->getPid()] = $process;
        }

        foreach ($old_process as $process) {
            $process->shutdown();
            $process->wait($block);
            unset($this->processes[$process->getPid()]);
        }
    }

    /**
     * keep sub process count
     *
     * @param bool $block block the master process
     * to keep the sub process count all the time
     * @param int $interval check time interval
     */
    public function keep($block = false, $interval = 100)
    {
        do {
            $this->start();

            // recycle sub process and delete the processes
            // which are not running from process list
            foreach ($this->processes as $process) {
                if (!$process->isRunning()) {
                    unset($this->processes[$process->getPid()]);
                }
            }

            $block ? usleep($interval) : null;
        } while ($block);
    }

    /**
     * return process count
     *
     * @return int
     */
    public function count()
    {
        return count($this->processes);
    }

    /**
     * get all processes
     *
     * @return Process[]
     */
    public function getProcesses()
    {
        return $this->processes;
    }
}