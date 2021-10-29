<?php

namespace Vega\Connector\Api\Traits;

/**
 * Trait Create
 * @package Vega\Connector\Api\Traits
 */
trait Create
{
    /**
     */
    private function createAction()
    {
        $dataLayer = $this->dataLayer->get();

        foreach ($dataLayer as $collection) {
            foreach ($collection as $item) {
                $this->clamp([$this, 'processCreate'], [$item], self::CLAMP_US_UNIT);
            }
        }

        return $this;
    }


    /**
     * @param $item
     */
    protected function processCreate($item): void
    {
        $data = $item->data;

        $key = data_get($data, data_get($this->config, 'key'));

        $accountData = data_get($this->config, 'account_data');

        try {
            $entityData = array_merge($data, $accountData);

            $functions = data_get($this->config, 'functions');

            foreach($functions as $function => $attributes) {
                //Enviar todo el array o un elemento iterativo 
                $limit = 1;
                $loopData = &$entityData;

                //Verificar que la función se llame mas de una vez
                if(isset($attributes['loop_call'])) {
                    $limit = count($entityData[$attributes['loop_call']]);
                    $loopData = &$entityData[$attributes['loop_call']];
                }

                $requestParametersExist = false;
                if(isset($attributes['request_parameters']) and (!empty($attributes['request_parameters']))) {
                    $requestParametersExist = true;
                }

                for($i=0; $i<$limit; ++$i) {
                    //Creacion del request
                    $requestData = $entityData;
                    if($requestParametersExist) {
                        $requestData = [];
                        foreach($attributes['request_parameters'] as $param => $value) {
                            $requestData[$param] = $loopData[$i][$value];
                        }
                    }

                    $entity = $this->callEntity($function, $requestData);

                    //Break del llamado
                    if(isset($attributes['break'])) {
                        for($i=0, $max=count($attributes['break']); $i<$max; ++$i) {
                            if(isset($entity[$attributes['break'][$i]['param']])) {
                                $breakExist = false;
                                switch($attributes['break'][$i]['type']) {
                                    case 'bool':
                                        $breakExist = ($entity[$attributes['break'][$i]['param']] == $attributes['break'][$i]['value']);
                                        break;
                                    case 'exist':
                                        $breakExist = isset($entity[$attributes['break'][$i]['param']]);
                                        break;
                                    default:
                                        break;
                                }

                                if($breakExist) {
                                    break(3);
                                }

                            }
                        }
                    }

                    //Añadir los datos de respuesta a la lista general
                    if(isset($attributes['add_data_next_request']) and $attributes['add_data_next_request']) {
                        $loopData[$i] = array_merge($loopData[$i], $entity);
                    }
                }
            }

            //log execution
            $this->executionService->success(
                $key,
                $this->getConnectorEntity(),
                $this->getConnectorAction()
            );

        } catch (\Exception $e) {
            $this->executionService->error(
                $key,
                $this->getConnectorEntity(),
                $this->getConnectorAction(),
                $e->getMessage()
            );

        }

    }
}
