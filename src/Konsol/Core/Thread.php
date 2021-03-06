<?php

namespace Konsol\Core;

class Thread
{
    const FUNCTION_NOT_CALLABLE = 10;
    const COULD_NOT_FORK        = 15;

    private $_error = array(
        10 => 'error 10',
        15 => 'error 15'
    );
    /**
     * Callback for the function that should run as a separate thread
     *
     * @var callback
     */
    protected $runnable;
    protected $return;

    /**
     * Holds the current process id
     *
     * @var integer
     */
    private $_pid;
    /**
     * Checks if threading is supported by the current PHP configuration
     *
     * @return boolean
     */
    public static function isAvailable()
    {
        $required_functions = array(
            'pnctl_fork',
        );

        foreach ( $required_functions as $function ) {
            if ( !function_exists($function) ) {
                return false;
            }
        }
        return true;
    }
    /**
     * Class constructor - you can pass
     * the callback function as an argument
     *
     * @param callback $runnable Callback reference
     */
    public function __construct( $runnable = null )
    {
        if ( $runnable !== null ) {
            $this->setRunnable($runnable);
        }
    }
    /**
     * Sets the callback
     *
     * @param callback $runnable Callback reference
     *
     * @return callback
     */
    public function setRunnable( $runnable )
    {
        if ( self::isRunnableOk($runnable) ) {
            $this->runnable = $runnable;
        } else {
            throw new \Exception(
                $this->getError(Thread::FUNCTION_NOT_CALLABLE),
                Thread::FUNCTION_NOT_CALLABLE
            );
        }
    }
    /**
     * Gets the callback
     *
     * @return callback
     */
    public function getRunnable()
    {
        return $this->runnable;
    }
    /**
     * Checks if the callback is ok (the function/method
     * actually exists and is runnable from the current
     * context)
     *
     * can be called statically
     *
     * @param callback $runnable Callback
     *
     * @return boolean
     */
    public static function isRunnableOk( $runnable )
    {
        return is_callable($runnable);
    }
    /**
     * Returns the process id (pid) of the simulated thread
     *
     * @return int
     */
    public function getPid()
    {
        return $this->_pid;
    }

    public function getReturn()
    {
        return $this->return;
    }

    /**
     * Checks if the child thread is alive
     *
     * @return boolean
     */
    public function isAlive()
    {
        $pid = pcntl_waitpid($this->_pid, $status, WNOHANG);
        return ( $pid === 0 );
    }
    /**
     * Starts the thread, all the parameters are
     * passed to the callback function
     *
     * @return void
     */
    public function start()
    {
        $pid = pcntl_fork();
        if ( $pid == -1 ) {
            throw new \Exception(
                $this->getError(Thread::COULD_NOT_FORK),
                Thread::COULD_NOT_FORK
            );
        }
        if ( $pid ) {
            // parent
            $this->_pid = $pid;
        } else {
            // child
            pcntl_signal(SIGTERM, array( $this, 'handleSignal' ));
            $arguments = func_get_args();
            if ( !empty($arguments) ) {
                $this->return = call_user_func_array($this->runnable, $arguments);
            } else {
                $this->return = call_user_func($this->runnable);
            }
            exit( 0 );
        }
    }
    /**
     * Attempts to stop the thread
     * returns true on success and false otherwise
     *
     * @param integer $signal SIGKILL or SIGTERM
     * @param boolean $wait   Wait until child has exited
     *
     * @return void
     */
    public function stop( $signal = SIGKILL, $wait = false )
    {
        if ( $this->isAlive() ) {
            posix_kill($this->_pid, $signal);
            if ( $wait ) {
                pcntl_waitpid($this->_pid, $status = 0);
            }
        }
    }
    /**
     * Alias of stop();
     *
     * @param integer $signal SIGKILL or SIGTERM
     * @param boolean $wait   Wait until child has exited
     *
     * @return void
     */
    public function kill( $signal = SIGKILL, $wait = false )
    {
        return $this->stop($signal, $wait);
    }
    /**
     * Gets the error's message based on its id
     *
     * @param integer $code The error code
     *
     * @return string
     */
    public function getError( $code )
    {
        if ( isset( $this->_errors[$code] ) ) {
            return $this->_errors[$code];
        } else {
            return "No such error code $code ! Quit inventing errors!!!";
        }
    }
    /**
     * Signal handler
     *
     * @param integer $signal Signal
     *
     * @return void
     */
    protected function handleSignal( $signal )
    {
        switch( $signal ) {
            case SIGTERM:
                exit( 0 );
                break;
        }
    }
}