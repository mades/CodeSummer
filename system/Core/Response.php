<?php

namespace CodeHuiter\Core;

use CodeHuiter\Config\Config;
use CodeHuiter\Config\ResponseConfig;
use CodeHuiter\Core\Exception\ExceptionProcessor;
use CodeHuiter\Exception\InvalidConfigException;
use CodeHuiter\Pattern\Service\Mjsa;

/**
 * TODO split response and html renderer
 */
class Response
{
    /** @var array */
    protected $finalHeaders = [];

    /** @var string */
    protected $finalOutput;

    /**
     * @var ResponseConfig
     */
    protected $config;

    protected $initLevel;

    protected $controller;

    /** @var Request */
    protected $request;

    protected $app;

    protected $cachedData = [];

    /**
     * @param Application $app
     * @param ResponseConfig $config
     * @param Request $request
     */
    public function __construct(Application $app, ResponseConfig $config, Request $request)
    {
        $this->initLevel = ob_get_level();
        $this->app = $app;
        $this->config = $config;
        $this->request = $request;
    }

    public function getRealViewFile($viewFile)
    {
        if (strpos($viewFile,':') === 0) {
            return VIEW_PATH . substr($viewFile, 1);
        }
        return $viewFile;
    }

    public function render($viewFile, $data = [], $return = false)
    {
        // TODO !!!!!! move to renderer

        $this->controller = Controller::getInstance();
        $those = $this->controller; // In view

        $_view_file = $viewFile;
        if (strpos($viewFile,':') === 0) {
            $_view_file = VIEW_PATH . substr($viewFile, 1);
        }

        if (strpos($_view_file, $this->config->templateNameAppend) === false) {
            $_view_file .= $this->config->templateNameAppend;
        }

        if (!file_exists($_view_file)) {
            ExceptionProcessor::defaultProcessException(
                new InvalidConfigException('Template [' . $_view_file . '] not found to process')
            );
        }

        if (!empty($data)) {
            $this->cachedData = array_merge($this->cachedData, $data);
        }
        extract($this->cachedData);

        ob_start();

        include($_view_file);

        if ($return === true) {
            $buffer = ob_get_contents();
            @ob_end_clean();
            return $buffer;
        }

        /*
         * Flush the buffer... or buff the flusher?
         *
         * In order to permit views to be nested within
         * other views, we need to flush the content back out whenever
         * we are beyond the first level of output buffering so that
         * it can be seen and included properly by the first included
         * template and any subsequent ones. Oy!
         */
        if (ob_get_level() > $this->initLevel + 1) {
            ob_end_flush();
        } else {
            $this->append(ob_get_contents());
            @ob_end_clean();
        }
    }

    public function html($string)
    {
        return htmlspecialchars($string);
    }

    protected function append($output)
    {
        $this->finalOutput .= $output;
        return $this;
    }

    /**
     * @param string $headerString
     */
    public function setHeader($headerString)
    {
        $headerArr = explode(':', $headerString);
        $this->finalHeaders[trim($headerArr[0])] = [$headerString];
    }

    public function denyIframe(): void
    {
        $this->setHeader('X-Frame-Options: DENY');
    }

    /**
     * @param $extensionOrFilename
     * @param string | null $charset null - Not send charset, 'default' - default response charset
     */
    public function setMimeType($extensionOrFilename, $charset = 'default')
    {
        /** @var \CodeHuiter\Config\Data\MimeTypes $mimeTypes */
        $mimeTypes = $this->app->get(Config::SERVICE_KEY_MIME_TYPES);
        if ($charset === 'default') {
            $charset = $this->config->charset;
        }
        $this->setHeader($mimeTypes->getTypeHeader($extensionOrFilename, $charset));
    }

    public function setCookie($name, $value, $expireTime, $path, $domain): void
    {
        setcookie($name, $value, $expireTime, $path, $domain);
    }

    public function sendHeaders(): void
    {
        foreach ($this->finalHeaders as $header) {
            header($header);
        }
        $this->finalHeaders = [];
    }


    public function send(): void
    {
        $this->sendHeaders();
        if ($this->config->profiler) {
            /** @var CodeLoader $loader */
            $loader = $this->app->get(Config::SERVICE_KEY_LOADER);
            $loader->benchmarkPoint('ResponseSend');
            if (strpos($this->finalOutput, '{#result_time_table}') !== false) {
                $this->finalOutput = str_replace('{#result_time_table}', $loader->benchmarkTotalTimeTable(), $this->finalOutput);
            }
            if (strpos($this->finalOutput, '{#result_class_table}') !== false) {
                $this->finalOutput = str_replace('{#result_class_table}', $loader->benchmarkTotalLoadedTable(), $this->finalOutput);
            }
            if (strpos($this->finalOutput, '{#result_time}') !== false) {
                $this->finalOutput = str_replace('{#result_time}', $loader->benchmarkElapsedString('BEFORE_SEND_RESPONSE'), $this->finalOutput);
            }
            if (strpos($this->finalOutput, '{#result_memory}') !== false) {
                $this->finalOutput = str_replace('{#result_memory}', $loader->benchmarkTotalMemoryString(), $this->finalOutput);
            }
        }

        echo $this->finalOutput;
    }

    /**
     * * @todo set and replace any uses 'set_status_header'
     *
     * @param int $code
     * @param string | null $text
     */
    public function setStatus($code, $text = null)
    {
        if ($this->request->isCli()) {
            return;
        }

        if ($text === null) {
            $code = (int) $code;

            if (isset(self::$httpCodes[$code])) {
                $text = self::$httpCodes[$code];
            } else {
                ExceptionProcessor::defaultProcessException(new InvalidConfigException(
                    'No status text available. Please check your status code number or supply your own message text.'
                ));
            }
        }

        if (strpos(PHP_SAPI, 'cgi') === 0) {
            header('Status: '.$code.' '.$text, TRUE);
        } else {
            $server_protocol = (isset($_SERVER['SERVER_PROTOCOL']) && in_array($_SERVER['SERVER_PROTOCOL'], array('HTTP/1.0', 'HTTP/1.1', 'HTTP/2'), TRUE))
                ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
            header($server_protocol.' '.$code.' '.$text, TRUE, $code);
        }
    }

    /** location
     * Редиректит на другю страницу в зависимости от запроса
     * @param string $url
     * @param boolean $temperatory Is 302 Moved Temperatory
     * @return boolean
     */
    public function location($url, $temperatory = false){
        if ($this->request->isMjsaAJAX()) {
            /** TODO !!!!! Extend Response to MjsaResponse */
            /** @var Mjsa $mjsaService */
            $mjsaService = $this->app->get('mjsa');
            $mjsaService->location($url, true);
        } else {
            if($temperatory === true){
                header("HTTP/1.0 302 Moved Temporarily");
            } else {
                header("HTTP/1.0 301 Moved Permanently");
            }
            header('Location: '.$url);
        }
        return false;
    }

    public const HTTP_CODE_FORBIDDEN = 403;
    public const HTTP_CODE_NOT_FOUND = 404;
    public const HTTP_CODE_MOVED_PERMANENTLY = 301;
    public const HTTP_CODE_MOVED_FOUND = 302;
    public static $httpCodes = [
        100	=> 'Continue',
        101	=> 'Switching Protocols',

        200	=> 'OK',
        201	=> 'Created',
        202	=> 'Accepted',
        203	=> 'Non-Authoritative Information',
        204	=> 'No Content',
        205	=> 'Reset Content',
        206	=> 'Partial Content',

        300	=> 'Multiple Choices',
        301	=> 'Moved Permanently',
        302	=> 'Found',
        303	=> 'See Other',
        304	=> 'Not Modified',
        305	=> 'Use Proxy',
        307	=> 'Temporary Redirect',

        400	=> 'Bad Request',
        401	=> 'Unauthorized',
        402	=> 'Payment Required',
        403	=> 'Forbidden',
        404	=> 'Not Found',
        405	=> 'Method Not Allowed',
        406	=> 'Not Acceptable',
        407	=> 'Proxy Authentication Required',
        408	=> 'Request Timeout',
        409	=> 'Conflict',
        410	=> 'Gone',
        411	=> 'Length Required',
        412	=> 'Precondition Failed',
        413	=> 'Request Entity Too Large',
        414	=> 'Request-URI Too Long',
        415	=> 'Unsupported Media Type',
        416	=> 'Requested Range Not Satisfiable',
        417	=> 'Expectation Failed',
        422	=> 'Unprocessable Entity',
        426	=> 'Upgrade Required',
        428	=> 'Precondition Required',
        429	=> 'Too Many Requests',
        431	=> 'Request Header Fields Too Large',

        500	=> 'Internal Server Error',
        501	=> 'Not Implemented',
        502	=> 'Bad Gateway',
        503	=> 'Service Unavailable',
        504	=> 'Gateway Timeout',
        505	=> 'HTTP Version Not Supported',
        511	=> 'Network Authentication Required',
    ];
}
