<?php

namespace Vega\Connector\Api;

use Vega\Connector\Rest\Connector as Rest;
use Vega\Core\Helpers\Data;
use Vega\Connector\Exceptions\ConnectorException;

abstract class Connector extends Rest
{
    public const VERSION = '1.0.0';
    public const MODULE_NAME = 'Api';

    public const RESULT_NODE = 'list';

    public const MAX_CONCURRENCE = 2;

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
        $this->addHeader('Content-Type', 'application/json');
        $this->auth($this->config['app_key'], $this->config['app_token']);
        parent::setup();
        $this->currentEntity = $this->getEntity($this->getConnectorEntity());
    }

    protected function auth($appKey, $appToken)
    {
        $this->addHeader('X-VTEX-API-AppKey', $appKey);
        $this->addHeader('X-VTEX-API-AppToken', $appToken);
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
}
