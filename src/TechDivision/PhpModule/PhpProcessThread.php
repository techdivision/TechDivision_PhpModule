<?php
/**
 * \TechDivision\PhpModule\PhpProcessThread
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category  Webserver
 * @package   TechDivision_PhpModule
 * @author    Johann Zelger <jz@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/TechDivision_PhpModule
 */

namespace TechDivision\PhpModule;

/**
 * Class PhpProcessThread
 *
 * @category  Webserver
 * @package   TechDivision_PhpModule
 * @author    Johann Zelger <jz@techdivision.com>
 * @copyright 2014 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/TechDivision_PhpModule
 */
class PhpProcessThread extends \Thread
{
    /**
     * Hold's the headers as array
     *
     * @var array
     */
    public $headers;

    /**
     * Hold's the output buffer generated by process run
     *
     * @var string
     */
    public $outputBuffer;

    /**
     * Hold's last error information as array
     *
     * @var array
     */
    public $lastError;

    /**
     * Hold's the uploaded filename's
     *
     * @var array
     */
    protected $uploadedFiles;

    /**
     * Constructs the process
     *
     * @param string                             $scriptFilename The script filename to execute
     * @param \TechDivision\PhpModule\PhpGlobals $globals        The globals instance
     * @param array                              $uploadedFiles  The uploaded files as array
     */
    public function __construct($scriptFilename, PhpGlobals $globals, array $uploadedFiles = array())
    {
        $this->scriptFilename = $scriptFilename;
        $this->globals = $globals;
        $this->uploadedFiles = $uploadedFiles;
    }

    /**
     * Run's the process
     *
     * @return void
     */
    public function run()
    {
        // register shutdown handler
        register_shutdown_function(array(&$this, "shutdown"));

        // init globals to local var
        $globals = $this->globals;

        // start output buffering
        ob_start();
        // set globals
        $_SERVER = $globals->server;
        $_REQUEST = $globals->request;
        $_POST = $globals->post;
        $_GET = $globals->get;
        $_COOKIE = $globals->cookie;
        $_FILES = $globals->files;

        // set http body content to this global var to be available for TYPO3 Neos
        $HTTP_RAW_POST_DATA = $globals->httpRawPostData;

        // register uploaded files for thread process context internal hashmap
        foreach ($this->uploadedFiles as $uploadedFile) {
            appserver_register_file_upload($uploadedFile);
        }

        // change dir to be in real php process context
        chdir(dirname($this->scriptFilename));
        // reset headers sent
        appserver_set_headers_sent(false);

        try {
            // require script filename
            require $this->scriptFilename;
        } catch (\Exception $e) {
            // echo uncought exceptions by default
            // todo: refactor this if pthreads can manage set_exception_handler.
            $this->lastError = array(
                'message' => $e->getMessage(),
                'type' => E_ERROR,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            );
        }

    }

    /**
     * Implements shutdown logic
     *
     * @return void
     */
    public function shutdown()
    {
        // save last error if not exist
        if (!$this->lastError) {
            $this->lastError = error_get_last();
        }
        // get php output buffer
        if (strlen($outputBuffer = ob_get_clean()) === 0) {
            if ($this->lastError['type'] == E_ERROR) {
                $errorMessage = 'PHP Fatal error: ' . $this->lastError['message'] .
                    ' in ' . $this->lastError['file'] . ' on line ' . $this->lastError['line'];
            }
            $outputBuffer = $errorMessage;
        }

        // todo: read out status line
        // set headers set by script inclusion
        $this->headers = appserver_get_headers(true);

        // set output buffer set by script inclusion
        $this->outputBuffer = $outputBuffer;
    }

    /**
     * Return's the output buffer
     *
     * @return string
     */
    public function getOutputBuffer()
    {
        return $this->outputBuffer;
    }

    /**
     * Return's the headers array
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Return's last error informations as array got from function error_get_last()
     *
     * @return array
     * @see error_get_last()
     */
    public function getLastError()
    {
        return $this->lastError;
    }
}
