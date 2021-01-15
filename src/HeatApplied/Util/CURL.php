<?php

namespace HeatApplied\Util;

class CURL {
    
    const MODE_JSON = 1;
    const MODE_FORMDATA = 2;
    
    const HTTP_GET = 'GET';
    const HTTP_POST = 'POST';
    const HTTP_PUT = 'PUT';
    const HTTP_PATCH = 'PATCH';
    const HTTP_DELETE = 'DELETE';
    
    const DEFAULT_DATA_FORMAT = '%s.%s';
    
    protected $mode;
    protected $method;
    protected $url;
    protected $params;
    protected $headers;
    protected $httpResponseCode;
    protected $responseHeaders;
    protected $response;
    protected $dataFormat;
    protected $cookieJar;
    
    public function __construct($method = null, $url = null, $params = [], $headers = []) {
        $this->mode = self::MODE_JSON;
        $this->method = self::HTTP_GET;
        $this->url = $url;
        $this->params = $params;
        $this->headers = $headers;
        $this->dataFormat = self::DEFAULT_DATA_FORMAT;
    }
    
    public function send() {
        if(!$this->isReady()) {
            return $this;
        }
        if($this->isSent()) {
            return $this;
        }
        $isJsonMode = $this->getMode() !== self::MODE_FORMDATA;
        $headers = $this->getHeaders();
        $params = $this->getParams();
        $method = $this->getMethod();
        $ch = curl_init();
        if($method == self::HTTP_GET) {
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
            if(!empty($params)) {
                $this->setUrl($this->getUrl() . Arrays::getAsDataUri($params, $this->getDataFormat()));
            }
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        else if ($method == self::HTTP_DELETE) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if(!empty($params)) {
                $this->setUrl($this->getUrl() . Arrays::getAsDataUri($params, $this->getDataFormat()));
            }
            if($isJsonMode) {
                $headers[] = 'Content-Type: application/json';
            }
            else {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }
        }
        else {
            $isMultipart = $this->isMultipartRequired();
            if($isMultipart) {
                $boundary = md5(time());
                $multipartData = $this->getMultipartData($boundary);
                $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
            }
            else if($isJsonMode) {
                $headers[] = 'Content-Type: application/json';
            }
            else {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }
            if (in_array($method, [self::HTTP_POST, self::HTTP_PUT, self::HTTP_PATCH])) {
                if (in_array($method, [self::HTTP_PUT, self::HTTP_PATCH])) {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                }
                else {
                    curl_setopt($ch, CURLOPT_POST, 1);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $isMultipart ? $multipartData : ($isJsonMode ? json_encode($params) : http_build_query($params)));
            } else {
                throw new \Exception('Unrecognized method: ' . $method);
            }
        }
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $this->getUrl());
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        $cookieJar = $this->getCookieJar();
        if(!empty($cookieJar)) {
            if(file_exists($cookieJar)) {
                curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
            }
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        }
        try {
            $fullResponse = curl_exec($ch);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headersAsString = substr($fullResponse, 0, $headerSize);
            $response = substr($fullResponse, $headerSize);
            $responseHeaders = [];
            $rawResponseHeaders = explode("\n", $headersAsString);
            $httpResponseCode = 200;
            foreach($rawResponseHeaders as $header) {
                $matches = [];
                $cleanHeader = trim(str_replace("\r", '', $header));
                if(($pos = strpos($cleanHeader, ':')) !== false) {
                    $responseHeaders[trim(substr($cleanHeader, 0, $pos))] = trim(substr($cleanHeader, $pos + 1));
                }
                elseif(preg_match('/HTTP\/[12]\.?[01]? (\d+)/i', $cleanHeader, $matches) >= 1) {
                    $httpResponseCode = (int)$matches[1];
                }
            }
            $this->setHttpResponseCode($httpResponseCode);
            $this->setResponseHeaders($responseHeaders);
            $this->setResponse($isJsonMode ? json_decode($response) : $response);
            curl_close($ch);
        }
        catch(\Exception $e) {
            curl_close($ch);
            throw new \Exception('Invalid CURL response', 9001);
        }
        return $this;
    }
    
    protected function getMultipartData($boundary) {
        $data = '';
        $delimiter = "\r\n";
        $params = $this->getParams();
        foreach($params as $key => $value) {
            $data .= '--' . $boundary . $delimiter;
            if(is_array($value) && isset($value['filename']) && isset($value['contents'])) {
                $data .= 'Content-Disposition: form-data; name="' . $key . '"; filename="' . $value['filename'] . '"' . $delimiter . 'Content-Type: application/octet-stream' . $delimiter . 'Content-Transfer-Encoding: 8bit' . $delimiter . $delimiter . $value['contents'] . $delimiter;
            }
            else if(is_object($value) && isset($value->filename) && isset($value->contents)) {
                $data .= 'Content-Disposition: form-data; name="' . $key . '"; filename="' . $value->filename . '"' . $delimiter . 'Content-Type: application/octet-stream' . $delimiter . 'Content-Transfer-Encoding: 8bit' . $delimiter . $delimiter . $value->contents . $delimiter;
            }
            else {
                $data .= 'Content-Disposition: form-data; name="' . $key . '"' . $delimiter . $delimiter . $value . $delimiter;
            }
        }
        return $data . "--" . $boundary . "--" . $delimiter . $delimiter;
    }
    
    protected function paramIsFile($param) {
        if(is_object($param)) {
            return !empty($param->filename) && !empty($param->contents);
        }
        return !empty($param['filename']) && !empty($param['contents']);
    }
    
    protected function isReady() {
        return !empty($this->url);
    }
    
    protected function isSent() {
        return !empty($this->response);
    }
    
    public function reset() {
        $this->method = null;
        $this->url = null;
        $this->params = [];
        $this->headers = [];
        $this->dataFormat = self::DEFAULT_DATA_FORMAT;
        return $this;
    }
    
    public function isMultipartRequired() {
        if(empty($this->params)) {
            return false;
        }
        foreach($this->params as $key => $value) {
            if(!$this->paramIsFile($value)) {
                continue;
            }
            return true;
        }
        return false;
    }
    
    public function getMode() {
        return $this->mode;
    }
    
    public function setMode($mode) {
        $this->mode = $mode;
        return $this;
    }
    
    public function getMethod() {
        return $this->method;
    }
    
    public function setMethod($method) {
        $this->method = $method;
        return $this;
    }
    
    public function getUrl() {
        return $this->url;
    }

    public function setUrl($url) {
        $this->url = $url;
        return $this;
    }
    
    public function getParams() {
        return $this->params;
    }
    
    public function setParams($params) {
        if(empty($params)) {
            $params = [];
        }
        $this->params = $params;
        return $this;
    }
    
    public function getHeaders() {
        return $this->headers;
    }
    
    public function setHeaders($headers) {
        $this->headers = $headers;
        return $this;
    }
    
    public function getDataFormat() {
        return $this->dataFormat;
    }
    
    public function setDataFormat($dataFormat) {
        $this->dataFormat = $dataFormat;
        return $this;
    }
    
    public function getCookieJar() {
        return $this->cookieJar;
    }
    
    public function setCookieJar($cookieJar) {
        $this->cookieJar = $cookieJar;
        return $this;
    }
    
    public function getHttpResponseCode() {
        return $this->httpResponseCode;
    }
    
    public function setHttpResponseCode($httpResponseCode) {
        $this->httpResponseCode = $httpResponseCode;
        return $this;
    }
    
    public function getResponseHeaders() {
        return $this->responseHeaders;
    }
    
    public function setResponseHeaders($responseHeaders) {
        $this->responseHeaders = $responseHeaders;
        return $this;
    }
    
    public function getResponse() {
        return $this->response;
    }
    
    public function setResponse($response) {
        $this->response = $response;
        return $this;
    }
    
}
