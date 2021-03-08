<?php
namespace OCA\Solid;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;

class PlainResponse extends Response {
    // FIXME: We might as well add a PSRResponse class to handle those;
    
    /**
     * response data
     * @var array|object
     */
    protected $data;

    /**
     * constructor of PlainResponse
     * @param array|object $data the object or array that should be transformed
     * @param int $statusCode the Http status code, defaults to 200
     */
    public function __construct($data='', $statusCode=Http::STATUS_OK) {
        parent::__construct();
        $this->data = $data;
        $this->setStatus($statusCode);
        $this->addHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Returns the data unchanged
     * @return string the data (unchanged)
     */
    public function render() {
        $response = $this->data;
        return $response;
    }

    /**
     * Sets the data for the response
     * @return PlainResponse Reference to this object
     */
    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    /**
     * Used to get the set parameters
     * @return response data
     */
    public function getData() {
        return $this->data;
    }
}
