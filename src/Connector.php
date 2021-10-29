<?php

namespace Vega\Connector\Api;

use Vega\Connector\Rest\Connector as Rest;
use Vega\Core\Helpers\Data;
use Vega\Connector\Exceptions\ConnectorException;

class Connector extends Rest
{
    use Traits\Clamp;
    use Traits\Create;

    public const VERSION = '1.0.0';
    public const MODULE_NAME = 'Api';

    public const RESULT_NODE = 'list';

    public const MAX_CONCURRENCE = 2;

    public const CLAMP_US_UNIT = 25000;


    /**
     * @var string
     */
    protected $requestDataType = 'body';

    /**
     * @var null|array|object
     */
    protected $currentEntity = null;

    /**
     * @throws \Vega\Connector\Exceptions\ConnectorException
     */
    protected function setup()
    {
        Data::replace(
            $this->config['base_uri'],
            [
                'site' => $this->config['site']
            ]
        );

        $this->setBaseUri($this->config['base_uri']);
        $this->addHeaders($this->config['headers']);
        parent::setup();
        $this->currentEntity = $this->getEntity($this->getConnectorEntity());
    }

    protected function addHeaders($headers)
    {
        foreach($headers as $key => $header) {
            $this->addHeader($key, $header);
        }
    }

    /**
     * @return bool
     */
    protected function isValidResponse()
    {
        return in_array($this->lastResponse->getStatusCode(), [200, 201, 204]);
    }

    /**
     * @param \Exception $exception
     * @param $key
     */
    protected function handleError(\Exception $exception, $key)
    {
        $message = $exception->getMessage();
        if ($exception instanceof ConnectorException && $exception->hasResponse()) {
            $response = $exception->getResponse();
            $error = data_get($response, "error");
            $message = data_get($error, "message");
        }
        $this->executionService->error(
            $key,
            $this->getConnectorEntity(),
            $this->getConnectorAction(),
            $message
        );
    }

    /**
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     * @param $entityPath
     * @param $data
     * @param bool $rawResponse
     * @return mixed
     * @throws ConnectorException
     */
    protected function callEntity($entityPath, $data = [], $rawResponse = false)
    {
        $entity = $this->getEntity($entityPath);

        if (!empty($data)) {
            Data::replace($entity->url, $data);
            $this->beforeCallEntity($entity, $data);
        }

        $headers = [];
        if(property_exists($entity, 'headers')) {
            $headers = $entity->headers;
        }

        $this->call($entity->url, $entity->method, $data, $headers, data_get($entity, 'base_uri'));

        if (!$rawResponse) {
            $this->afterCallEntity($entity);
        }

        return $this->parsedResponse;
    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        $this->createAction();
    }

}
