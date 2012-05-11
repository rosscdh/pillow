<?php
namespace Api\Pillow;
/**
 * Magic call Api interface method
 * uses call to magically call remote api methods
 * 
 * Oauth2 Example - Calling standard Oauth2 interface
 * 
 *         $api = new Api\Pillow\Pillow('http://localhost/');
 * 
 *         $access_token = $api->v1OauthAccess_token()->post(array(
 *             'client_id'          => '<client_code>',
 *             'client_secret'      => '<client_secret>',
 *             'scope'              => 'none',
 *         ));
 * 
 *      Please Note the $api->v1OauthAccess_token() <-- this is your url from the initializing host
 *      http://localhost/v1/oauth/access_token/ <-- Pillow converts to lowercase and *always* appends / to the url
 * 
 * 
 * @package default
 * @author Ross Crawford-d'Heureuse
 * @email sendrossemail+pillow@gmail.com
 */
class Pillow{
    
    private 
        $api_url,               // injected url to be called (environment specific)
        $api_method,            // remote method to be called http://api_url/methodName?query=
        $query_string = null,           // query string to be passed in
        $post = array(),        // *optional items to be posted in
        $request_url,           // instance request_url not urlencoded (local vars are urlencoded for logging)
        $response;              // remote api response object (xml to object/json/xml)

    private
        $curl,                  // local curl instance
        $logger,                // injected logger instance
        $CURL_SETTINGS = array(
            'CURLOPT_CONNECTTIMEOUT'    => 15,
            'CURLOPT_TIMEOUT'           => 15,
            'CURLOPT_MAXCONNECTS'       => 3,
        );

    /**
     * Construct accepts dependency injected parameters
     * implemented according to symfony docs (http://symfony.com/doc/2.0/book/service_container.html)
     * @param string $api_url 
     * @param string $logger 
     * @author Ross Crawford-d'Heureuse
     */
    public function __construct($api_url, $logger=false, $authorization=false)
    {
        $this->setApiUrl($api_url);
        $this->authorization = $authorization;
        $this->logger = $logger;
    }

    public function setApiUrl($url)
    {
        $this->api_url = $url;
    }

    public function getApiUrl()
    {
        return $this->api_url;
    }

    private function log($msg) {
        if ($this->logger) {
            $this->logger->info($msg);
        }
    }

    /**
     * Magic call method to process remote api method calls which may have different method signatures
     * remote api call updateDomain?domain= is instanced like $this->updateDomain(array('domain'=>$remote_domain))
     * 
     * @param string $name 
     * @param Array $arguments 
     * @return void
     * @author Ross Crawford-d'Heureuse
     */
    public function __call($name, Array $arguments)
    {
        // normalise into expected query
        if ($name == 'SimpleCall')
        {
            $this->api_method = null;
        }else{
            //$api_method = lcfirst(self::camelize($name)); // from Monkey__method_Call into monkeyMethodCall
            $api_method = $name;
            $this->api_method = strtolower(implode('/', preg_split('/(?=[A-Z])/',$api_method)));
        }
        return $this;
    }

    /**
     * Set post params to be included in curl post
     *
     * @param string $key 
     * @param string $value 
     * @return void
     * @author Ross Crawford-d'Heureuse
     */
    public function setPostParam($key, $value)
    {
        // key exists
        if (isset($this->post[$key]))
        {
            // is it already an array
            if (is_array($this->post[$key]))
            {
                // jsut append to array
                $this->post[$key][] = $value;
            }else{
                // convert it into an array, preserving the current value and making it the first element
                $current_value_tmp = $this->post[$key];
                $this->post[$key] = array($current_value_tmp, $value);
                unset($current_value_tmp);
            }

        }else{
            $this->post[$key] = $value;
        }
    }

    /**
     * Get current post values
     *
     * @return false or Array
     * @author Ross Crawford-d'Heureuse
     */
    private function getPost()
    {
        return (count($this->post) == 0) ? false : $this->post;
    }

    /**
     * Build a fully qualified api uri complete with querystring
     * used at the point of the remote api call
     *
     * @return string
     * @author Ross Crawford-d'Heureuse
     */
    public function getApiQueryUrl()
    {
        if ($this->query_string != '') {
            return sprintf('%s%s/?%s',$this->api_url, $this->api_method, $this->query_string);
        } else {
            return sprintf('%s%s/',$this->api_url, $this->api_method);
        }
    }

    /**
     * Get local instance of curl and set it up for quick api useage
     *
     * @return String XML $response
     * @author Ross Crawford-d'Heureuse
     */
    private function getCurl()
    {
        $request_url = $this->request_url = $this->getApiQueryUrl();

        $this->log(sprintf('Curl Request to: %s', urldecode($request_url)));

        $curl = curl_init($request_url);


        if ($post = $this->getPost())
        {
            //Do a regular HTTP POST? (yes)
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->getPost());
        }

        if ($this->authorization && isset($this->authorization['auth_name']) && isset($this->authorization['auth_pass']))
        {
            curl_setopt($curl, CURLOPT_USERPWD, $this->authorization['auth_name'].':'.$this->authorization['auth_pass']);
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        // ssl settings
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        //Include header in result? (no)
        curl_setopt($curl, CURLOPT_HEADER,  false);
        //Some servers (like Lighttpd) will not process the curl request without this header and will return error code 417 instead. 
        //Apache does not need it, but it is safe to use it there as well.
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Expect:"));
        //register a callback function which will process the headers
        //this assumes your code is into a class method, and uses $this->readHeader as the callback //function
        #curl_setopt($curl, CURLOPT_HEADERFUNCTION, array(&$this,'logCurlHeaders'));
        //. timeout of the initial connection attempt! Very important when large number of curl requests go out
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->CURL_SETTINGS['CURLOPT_CONNECTTIMEOUT']);
        //The maximum number of seconds to allow cURL to execute
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->CURL_SETTINGS['CURLOPT_TIMEOUT']); 
        //. maximum number of urls to have open before older connections start being closed
        curl_setopt($curl, CURLOPT_MAXCONNECTS, $this->CURL_SETTINGS['CURLOPT_MAXCONNECTS']); 
        //Return the transfer as a string - instead of printing it
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //. dont reuse connections!
        curl_setopt($curl, CURLOPT_FORBID_REUSE, false);
        // works hand in hand with CURLOPT_FORBID_REUSE if CURLOPT_FORBID_REUSE == true then this must be CURLOPT_FRESH_CONNECT == false
        //. curl wtf?
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, false);
        //The "User-Agent" header to be used in a HTTP request
        curl_setopt($curl, CURLOPT_USERAGENT, "Pillow/0.0.1");

        if (!$response = curl_exec($curl))
        {
            $this->log(sprintf('Curl Request to: %s\r\n%s', $request_url, curl_error($curl)));
        }

        $this->log(sprintf('Curl XML Doc: %s - %s', $this->request_url, $response));

        return trim($response);

    }

    private function logCurlHeaders($data)
    {
        $this->log(sprintf('Request Headers : %s - %s', $this->request_url, $data));
    }

    public function fetchApiQueryResponse($as='json')
    {
        try{
            $response = $this->getCurl();
        }catch(Exception $e){
            $this->log(sprintf('Unable to parse XML Doc: %s - %s', $this->request_url, $e->getMessage()));
        }
        if ($response)
        {
            return $this->getAs($response, $as);
        }else{
            $this->log(sprintf('Unable to parse XML Doc: %s - %s', $this->request_url, $response));
            return false;
        }
        
    }

    /**
     * Convert a valid XML response to given type
     * or return as plain XML (or format provided by remote api)
     *
     * @param string $response 
     * @param string $type xml|default OR object|SimpleXML_Object OR json|JSON_Object
     * @return multiple (XML/SimpleXML_Object/json)
     * @author Ross Crawford-d'Heureuse
     */
    private function getAs($response, $type)
    {
        $this->log(sprintf('XML RESPONSE: %s', $response));

        if ($type == 'json')
        {
            $this->log(sprintf('Converted XML Doc to %s: %s', $type, $response));
            return json_decode($response);

        }else{
            // plain old xml/whatever the system returns
            return $response;
        }
    }

    public function get(Array $arguments=null, $as='json') {
        if (isset($arguments) && is_array($arguments) && count($arguments) > 0)
        {
            $this->query_string = http_build_query($arguments[0]);
        }
        return $this->fetchApiQueryResponse($as);
    }

    public function post(Array $arguments=null, $as='json') {
        if (isset($arguments) && is_array($arguments) && count($arguments) > 0)
        {
            foreach ($arguments as $k => $v) {
                $this->setPostParam($k, $v);
            }
        }

        return $this->fetchApiQueryResponse($as);
    }

    static public function camelize($id)
    {
        return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) { return ('.' === $match[1] ? '_' : '').strtoupper($match[2]); }, $id);
    }
}
