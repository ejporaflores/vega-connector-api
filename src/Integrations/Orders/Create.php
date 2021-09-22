<?php

namespace Vega\Connector\Api\Integrations\Orders;

use Illuminate\Http\Response;
use Vega\Connector\Api\Connector;
use Vega\Connector\Exceptions\ConnectorException;
use Vega\Connector\Api\Traits\Clamp;

/**
 * Class Create
 * @package Vega\Connector\Connectors\Api\Orders
 */
class Create extends Connector
{
    use Clamp;



    /**
     * @return $this
     */
    public function execute()
    {
        $orders = $this->dataLayer->get();
$archivo = fopen('public/z.txt', 'a+');

        foreach ($orders as $collection) {
fwrite($archivo, json_encode($collection) . PHP_EOL);
/*
            foreach ($collection as $item) {
                $this->clamp([$this, 'processUpdate'], [$item], self::CLAMP_US_UNIT);
            }
*/
        }
fclose($archivo);
        return $this;
    }

    /**
     * @return bool
     */
    private function canConfirm()
    {
        if (
            property_exists($this->currentEntityConfig, 'confirm')
            && $this->currentEntityConfig->confirm
        ) {
            return true;
        }
        return false;
    }

    /**
     * @param $item
     * @return mixed
     */
    protected function getItemKey($item)
    {
        return property_exists($this->currentEntity, 'key') ? data_get(
            $item,
            $this->currentEntity->key
        ) : $item[self::ORDER_KEY];
    }

    /**
     * @param $page
     * @return mixed
     * @throws ConnectorException
     */
    protected function getOrderPage($page)
    {
        $status = property_exists(
            $this->currentEntity,
            'status'
        ) ? $this->currentEntity->status : self::ORDER_STATUS;
        //fetch orders collection
        return $this->callEntity("orders.get", ['page' => $page, 'status' => $status]);
    }

    /**
     * @param $orderReference
     * @return boolean
     */
    protected function processPull($orderReference): bool
    {
        $key = data_get($orderReference, $this->getCurrentEntityConfig('key'));
        try {
            //fetch order entity
            $item = $this->callEntity("order.get", ['order_id' => $key]);
            //@todo refactor not take the model
            if (array_key_exists('customer_email', $item)) {
                $email = $this->callEntity(
                    "email.get",
                    ['account' => data_get($this->config, 'account'), 'alias' => $item['customer_email']]
                );

                $item = array_merge($item, $email);
            }
            //push order to data layer
            $this->dataLayer->push($item);

            if ($this->canConfirm()) {
                try {
                    $this->callEntity("invoices.confirm", $item, true);
                } catch (ConnectorException $ce) {
                    if ($ce->getCode() != Response::HTTP_CONFLICT) {
                        throw $ce;
                    }
                }
            }
            //log execution
            $this->executionService->success(
                $key,
                $this->getConnectorEntity(),
                $this->getConnectorAction()
            );
            return true;
        } catch (\Exception $e) {
            $this->executionService->error(
                $key,
                $this->getConnectorEntity(),
                $this->getConnectorAction(),
                $e->getMessage()
            );
            return false;
        }
    }

    /**
     * get orders wrapper
     */
    protected function getOrders()
    {
        $realMethod = 'getOrders' . $this->mode;
        $this->$realMethod();
    }

    /**
     * Paged orders
     */
    protected function getOrdersPaged()
    {
        $page = 1;
        do {
            $response = $this->getOrderPage($page);
            foreach ($response[self::RESULT_NODE] as $item) {
                $this->orders[] = $item;
            }
            $pages = data_get($response, 'paging.pages');
            $page++;
        } while ($page <= $pages);
    }

    /**
     * Feed orders
     */
    protected function getOrdersFeed()
    {
        $maxCalls = $this->feedConfig->maxcalls ?? self::FEED_MAX_CALLS;
        $orderKey = $this->getCurrentEntityConfig('key');
        $calls = 0;
        while ($calls < $maxCalls && $items = $this->callEntity('feed.get')) {
            $calls++;
            foreach ($items as $item) {
                // matcheamos con la key de orders
                $item[$orderKey] = $item[$this->feedConfig->key];
                // por nro de orden para evitar eventuales repeticiones al pisar
                $this->orders[$item[$this->feedConfig->key]] = $item;
            }
        }
    }

    /**
     * inicializar arrays
     */
    protected function beforeProcess()
    {
        $this->orders = [];
        $this->handles = [];
    }

    /**
     * wrapper after process
     */
    protected function afterProcess()
    {
        $realMethod = 'afterProcess' . $this->mode;
        $this->$realMethod();
    }

    /**
     * after process paged
     */
    protected function afterProcessPaged()
    {
        // paginado no hay acciones after process
    }

    /**
     * @throws ConnectorException
     */
    protected function afterProcessFeed()
    {
        $handles = array_chunk($this->handles, static::FEED_MAX_HANDLES);
        if (count($handles)) {
            foreach ($handles as $handleChunk) {
                $this->callEntity('feed.confirm', ['handles' => $handleChunk]);
            }
        }
    }

    /**
     * wrapper after pull
     * @param $item
     * @param $result
     */
    protected function afterPull($item, $result)
    {
        $realMethod = 'afterPull' . $this->mode;
        $this->$realMethod($item, $result);
    }

    /**
     * after pull paged
     * @param $item
     * @param $result
     */
    protected function afterPullPaged($item, $result)
    {
        // paginado no hay acciones afterPull
    }

    /**
     * after pull feed
     * @param $item
     * @param $result
     */
    protected function afterPullFeed($item, $result)
    {
        if ($result) {
            $this->handles[] = $item['handle'];
        }
    }
}
