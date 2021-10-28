<?php

namespace Vega\Connector\Api;

use Vega\Connector\Rest\Connector as Rest;
use Vega\Core\Helpers\Data;
use Vega\Connector\Exceptions\ConnectorException;

class Connector extends Rest
{
    use Traits\Clamp;

    public const VERSION = '1.0.0';
    public const MODULE_NAME = 'Api';

    public const RESULT_NODE = 'list';

    public const MAX_CONCURRENCE = 2;

    /**
     * @throws \Exception
     */
    public function execute()
    {
$archivo = fopen('public/z.txt', 'a+');
fwrite($archivo, '############## execute ###########' . PHP_EOL . PHP_EOL);
fclose($archivo);
        $this->createAction();
    }

}
