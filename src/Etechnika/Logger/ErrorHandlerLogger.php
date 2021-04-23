<?php

namespace Etechnika\Logger;

/*
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Easy login error to files
 *
 * @package    Etechnika
 * @subpackage Logger
 * @author     Tomasz Rutkowski
 * @version    0.1
 */
class ErrorHandlerLogger
{
    /**
     * @var integer
     */
    const OPTION_LOG_DIR_PATH                   = 1000; // Path to log dir
    const OPTION_LOG_ROTATE                     = 1001; // Rotate log by date
    const OPTION_LOG_FILE_NAME_FATAL_ERROR      = 1010; // Fatal error file name
    const OPTION_LOG_FILE_NAME_EXCEPTION        = 1011; // Uncatchable exception file name
    const OPTION_LOG_FILE_NAME_OTHER            = 1012; // Other error file name
    const OPTION_LOG_FILE_NAME_DEPRECATED       = 1013; // Deprecated error file name
    const OPTION_LOG_SIZE_LIMIT                 = 1020; // Log file size limit
    const OPTION_LOG_EMAIL_ADDRESS              = 2000; // Email address receive errors (only fatal error)

    /**
     * @var boolean
     */
    private $booLogRotate = false;

    /**
     * Default path is the same as the class directory
     *
     * @var string
     */
    private $strLogDirPath = '';

    /**
     * @var string
     */
    private $strLogFileNameFatalError = 'fatal_error';

    /**
     * @var string
     */
    private $strLogFileNameException = 'exception';

    /**
     * @var string
     */
    private $strLogFileNameDeprecated = 'deprecated';

    /**
     * @var string
     */
    private $strLogFileNameOther = 'other';


    /**
     * File size limit in megabyte
     *
     * No limit when null
     *
     * @var integer
     */
    private $intLogFileSizeLimit = null;


    /**
     * @var string
     */
    private $strLogEmailAddress = '';


    /**
     * @var mixed
     */
    private $mixPrevErrorHandler;


    /**
     * @var mixed
     */
    private $mixPrevExceptionHandler;


    /**
     * Error description
     *
     * @var array
     */
    private static $arrErrorNames = array(
            E_ERROR                 => 'Error',
            E_WARNING               => 'Warning',
            E_PARSE                 => 'Parse',
            E_NOTICE                => 'Notice',
            E_CORE_ERROR            => 'Code error',
            E_CORE_WARNING          => 'Core warning',
            E_COMPILE_ERROR         => 'Compile error',
            E_COMPILE_WARNING       => 'Compile warning',
            E_USER_ERROR            => 'User error',
            E_USER_WARNING          => 'User Warning',
            E_USER_NOTICE           => 'User Notice',
            E_STRICT                => 'Strict',
            E_RECOVERABLE_ERROR     => 'Recoverable error',
            E_DEPRECATED            => 'Deprecated',
            E_USER_DEPRECATED       => 'User deprecated',
            E_ALL                   => 'All',
    );


    /**
     * Setting logger options
     *
     * @param array $arrOptions
     */
    public function setOptions($arrOptions = null)
    {
        if (is_null($arrOptions) || !is_array($arrOptions)) {
            return;
        }

        foreach($arrOptions as $intOption => $mixValue) {
            switch((int)$intOption) {
                case self::OPTION_LOG_DIR_PATH:
                    $this->strLogDirPath = (string) $mixValue;
                    if (!is_dir($this->strLogDirPath . DIRECTORY_SEPARATOR)) {
                        throw new \Exception('Invalid log directory');
                    }
                    break;
                case self::OPTION_LOG_ROTATE:
                    $this->booLogRotate = (boolean) $mixValue;
                    break;
                case self::OPTION_LOG_FILE_NAME_FATAL_ERROR:
                    $this->strLogFileNameFatalError = (string) $mixValue;
                    break;
                case self::OPTION_LOG_FILE_NAME_EXCEPTION:
                    $this->strLogFileNameException = (string) $mixValue;
                    break;
                case self::OPTION_LOG_FILE_NAME_DEPRECATED:
                    $this->strLogFileNameDeprecated = (string) $mixValue;
                    break;
                case self::OPTION_LOG_FILE_NAME_OTHER:
                    $this->strLogFileNameOther = (string) $mixValue;
                    break;
                case self::OPTION_LOG_SIZE_LIMIT:
                    $this->intLogFileSizeLimit = is_null($mixValue) ? null : (int) $mixValue;
                    break;
                case self::OPTION_LOG_EMAIL_ADDRESS:
                    $this->strLogEmailAddress = (string) $mixValue;
                    break;
                default:
                    throw new \Exception('Unknown options with id '. var_export($intOption, true));
            }
        }
    }

    /**
     * Return errors names
     *
     * @return array
     */
    protected function getErrorNames()
    {
        return static::$arrErrorNames;
    }

    /**
     * Register all handler
     *
     * @param array $arrOptions
     */
    public static function registerAll($arrOptions = null)
    {
        $objHandler = new static();
        $objHandler->setOptions($arrOptions);
        $objHandler->registerShutdown();
        $objHandler->registerErrorHandler();
        $objHandler->registerExceptionHandler();
        $objHandler->registerAssertionHandler();
    }

    /**
     * Register error handler
     *
     * @return mixed
     */
    public function registerErrorHandler()
    {
        $this->mixPrevErrorHandler = set_error_handler(array($this, 'errorHandler'));

        return $this->mixPrevErrorHandler;
    }

    /**
     * Error handler function
     *
     * @param integer $errno
     * @param string $errstr
     * @param string $errfile
     * @param itneger $errline
     * @param array $errcontext
     * @return boolean
     */
    public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        switch ( $errno ) {
            case E_RECOVERABLE_ERROR:
                $this->notifyOther($errno, $errstr, $errfile, $errline);
                if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                    return $this->mixPrevErrorHandler;
                }
                return false;
                break;

            case E_STRICT:
                // no break
            case E_NOTICE:
                // no break
            case E_WARNING:
                // no break
            case E_USER_WARNING:
                // no break
            case E_USER_NOTICE:
                $this->notifyOther($errno, $errstr, $errfile, $errline);
                break;

            case E_DEPRECATED:
            // no break
            case E_USER_DEPRECATED:
            $this->notifyDeprecated($errno, $errstr, $errfile, $errline);
                break;
        }

        return $this->mixPrevErrorHandler;
    }

    /**
     *
     * @param string $strFileName
     * @param integer $intLevel
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     */
    private function genericNotifyToFile($strFileName, $intLevel, $strErrstr, $strErrfile, $intErrline)
    {
        $arrErrors = static::getErrorNames();
        if ( array_key_exists($intLevel, $arrErrors) ) {
            $strLevel = $arrErrors[$intLevel];
        }
        else {
            $strLevel = 'Undefined ('. var_export( $intLevel, true ) .')';
        }

        if (empty($this->strLogDirPath)) {
            $this->strLogDirPath = dirname( __FILE__ );
        }

        $this->prepareDir();

        $strMessage = "\n-----\n";
        $strMessage .= "Time:  ". date( 'Y-m-d H:i:s' ) ."\nLevel: $intLevel ($strLevel)\nError: $strErrstr\nFile:  $strErrfile\nLine:  $intErrline\n";

        $fnFilter = function($v) {
            return in_array($v, array(
                    'HTTP_REFERER',
                    'REQUEST_URI',
                    'REDIRECT_QUERY_STRING',
                    'CONTENT_TYPE',
                    'HTTP_HOST',
                    'REQUEST_METHOD',
                    'SCRIPT_FILENAME',
            ));
        };

        $arrTmp = $_SERVER;
        $arrTmpKeys = array_filter(array_keys($arrTmp), $fnFilter);
        $arrTmp = array_intersect_key($arrTmp, array_flip($arrTmpKeys) );
        ksort($arrTmp);
        $strMessage .= "Server:\n";
        foreach( $arrTmp as $k => $v ) {
            $strMessage .= "  $k: $v\n";
        }

        $strFullPath = $this->getLogDirPath() . DIRECTORY_SEPARATOR . $strFileName;
        if ($this->isFileSizeOverLimit($strFullPath)) {
            return false;
        }

        error_log($strMessage, 3, $strFullPath);
    }

    /**
     *
     * @param integer $intLevel
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     */
    private function notifyDeprecated($intLevel, $strErrstr, $strErrfile, $intErrline)
    {
        $this->genericNotifyToFile($this->strLogFileNameDeprecated .'.txt', $intLevel, $strErrstr, $strErrfile, $intErrline);
    }

    /**
     *
     * @param integer $intLevel
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     */
    private function notifyOther($intLevel, $strErrstr, $strErrfile, $intErrline)
    {
        $this->genericNotifyToFile($this->strLogFileNameOther .'.txt', $intLevel, $strErrstr, $strErrfile, $intErrline);
    }

    /**
     *
     * @return mixed
     */
    public function registerExceptionHandler()
    {
        $this->mixPrevExceptionHandler = set_exception_handler(array($this, 'exceptionHandler'));

        return $this->mixPrevExceptionHandler;
    }

    /**
     * @param Exception $e
     * @return boolean
     */
    function exceptionHandler( $e )
    {
        $strMessage = "\n-----\n";
        $strMessage .= "Time:      ". date( 'Y-m-d H:i:s' ) ."\nException: ". get_class($e) ."\nMessge:    ". $e->getMessage() ."\n";
        $strMessage .= "File:      ". $e->getFile() ."\nLine:      ". $e->getLine() ."\n";

        $strMessage .= "Trace:\n";
        foreach(explode("\n", $e->getTraceAsString() ) as $strLine) {
            $strMessage .= "  ". $strLine ."\n";
        }

        $fnFilter = function($v) {
            return in_array($v, array(
                    'HTTP_REFERER',
                    'REQUEST_URI',
                    'REDIRECT_QUERY_STRING',
                    'CONTENT_TYPE',
                    'HTTP_HOST',
                    'REQUEST_METHOD',
                    'SCRIPT_FILENAME',
            ));
        };

        $arrTmp = $_SERVER;
        $arrTmpKeys = array_filter(array_keys($arrTmp), $fnFilter);
        $arrTmp = array_intersect_key($arrTmp, array_flip($arrTmpKeys) );
        ksort($arrTmp);
        $strMessage .= "Server:\n";
        foreach( $arrTmp as $k => $v ) {
            $strMessage .= "  $k: $v\n";
        }

        $this->prepareDir();
        $strFullPath = $this->getLogDirPath() . DIRECTORY_SEPARATOR . $this->strLogFileNameException  .'.txt';

        if ($this->isFileSizeOverLimit($strFullPath)) {
            return false;
        }
        error_log($strMessage, 3, $strFullPath);

        return $this->mixPrevExceptionHandler;
    }

    /**
     * @return mixed
     */
    public function registerAssertionHandler()
    {
        return assert_options(ASSERT_CALLBACK, array($this, 'assertionHandler'));
    }

    /**
     * @param string $file File source of assertion
     * @param integer $line Line source of assertion
     * @param mixed $code Assertion code
     * @throw \ErrorException
     */
    public function assertionHandler($file, $line, $code)
    {
        throw new \ErrorException('Assertion Failed - Code[ ' . $code . ' ]', 0, null, $file, $line);
    }

    /**
     * Register shutdown function login errors to file
     *
     * @return
     */
    public function registerShutdown()
    {
        $fn = function( $objLogger ) {
            if ( false ) { $objLogger = new self(); }

            $objLogger->prepareDir();

            $arrErrors = error_get_last();
            if (is_null($arrErrors)) {
                return;
            }

            // Fatal Error itp
            if ( $arrErrors['type'] == E_ERROR ) {
                $strFileContent = "\n-----\nTime: ". date('Y-m-d H:i:s') ."\nType: {$arrErrors['type']} (E_ERROR)\n";
                $strFileContent .= "Message: {$arrErrors['message']}\nFile: {$arrErrors['file']}\nLine: {$arrErrors['line']}\n";

                $strFileContent .= "======= \$_REQUEST =================================\n";
                $strFileContent .= var_export( $_REQUEST, true ) ."\n";
                $strFileContent .= "======= \$_SERVER ==================================\n";
                $arrTmp = $_SERVER;

                $fnFilter = function($v) {
                    return ! in_array($v, array(
                            'DOCUMENT_ROOT',
                            'GATEWAY_INTERFACE',
                            'HTTP_ACCEPT',
                            'HTTP_ACCEPT_ENCODING',
                            'HTTP_ACCEPT_LANGUAGE',
                            'HTTP_CACHE_CONTROL',
                            'HTTP_CONNECTION',
                            'HTTP_USER_AGENT',
                            'PATH',
                            'SERVER_PROTOCOL',
                            'REQUEST_TIME',
                            'SERVER_SIGNATURE',
                            'SERVER_PORT',
                            'SERVER_NAME',
                            'SERVER_ADMIN',
                            'SERVER_SOFTWARE',
                            'SERVER_ADDR',
                    ));
                };

                $arrTmpKeys = array_filter(array_keys($arrTmp), $fnFilter);
                $arrTmp = array_intersect_key($arrTmp, array_flip($arrTmpKeys) );
                ksort( $arrTmp );

                $strFileContent .= var_export( $arrTmp, true ) ."\n";
                $strFileContent .= "====================================================\n";

                $strFileFullePath = $objLogger->getLogDirPath() . DIRECTORY_SEPARATOR . $objLogger->getLogFileNameFatalError() .'.txt';
                error_log($strFileContent, 3, $strFileFullePath);

                $strEmail = $objLogger->getLogEmailAddress();
                if (!empty($strEmail)) {
                    error_log($strFileContent, 1, $strEmail);
                }
                return;
            } // endif
        };

        register_shutdown_function($fn, $this);
    }

    /**
     * Return log dir path
     *
     * @return string
     */
    public function getLogDirPath()
    {
        $strBasePath = $this->strLogDirPath;
        if (!$this->booLogRotate) {
            return $strBasePath;
        }

        return $strBasePath . DIRECTORY_SEPARATOR . date( 'Y' ) . DIRECTORY_SEPARATOR . date( 'm' ) . DIRECTORY_SEPARATOR . date( 'd' );
    }

    /**
     * Prepare logdirectory
     *
     * @throws \Exception
     */
    public function prepareDir()
    {
        if (empty($this->strLogDirPath)) {
            $this->strLogDirPath = dirname( __FILE__ );
        }

        $strFullDirPath = $this->getLogDirPath();
        if (!is_dir($strFullDirPath)) {
            if (mkdir($strFullDirPath, 0755, true) !== true ) {
                throw new \Exception('Create directory '. var_export($strFullDirPath, true) .' error.');
            }
        }
    }

    /**
     *
     * @return string
     */
    public function getLogEmailAddress()
    {
        return $this->strLogEmailAddress;
    }

    /**
     *
     * @return string
     */
    public function getLogFileNameFatalError()
    {
        return $this->strLogFileNameFatalError;
    }

    /**
     * Check file size limit exceed
     *
     * @param string $strFullPath
     * @return boolean
     */
    private function isFileSizeOverLimit($strFullPath)
    {
        if (is_null($this->intLogFileSizeLimit)) {
            return false;
        }

        if (! file_exists($strFullPath)) {
            return false;
        }

        $intFileSizeInByte = $this->intLogFileSizeLimit * 1024 * 1024;
        if ($intFileSizeInByte < filesize($strFullPath)) {
            return true ;
        }

        return false;
    }
}
