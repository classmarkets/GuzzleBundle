<?php

namespace Playbloom\Bundle\GuzzleBundle\DataCollector;

use Throwable;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

class CompositeGuzzleDataCollector extends DataCollector
{
    /** @var DataCollectorInterface[] */
    private $collectors;

    public function __construct(...$collectors)
    {
        $this->collectors = $collectors;
    }

    public function getName()
    {
        return 'guzzle';
    }

    public function collect(Request $request, Response $response, Throwable $exception = null)
    {
        $allData = [
            'total_time'  => 0,
            'error_count' => 0,
            'calls'       => [],
            'methods'     => []
        ];

        foreach ($this->collectors as $collector) {
            $data = $collector->collect($request, $response, $exception);
            foreach ($data['methods'] as $method => $methodCounter) {
                if (array_key_exists($method, $allData) === false) {
                    $allData['methods'][$method] = 0;
                }
                $allData['methods'][$method] += $methodCounter;
            }

            $allData['total_time'] += $data['total_time'];
            $allData['error_count'] += $data['error_count'];

            if (count($data['calls']) > 0) {
                $allData['calls'] = array_merge($allData['calls'], $data['calls']);
            }
        }

        $this->data = $allData;
    }

    /**
     * @return array
     */
    public function getCalls()
    {
        return isset($this->data['calls']) ? $this->data['calls'] : array();
    }

    /**
     * @return int
     */
    public function countErrors()
    {
        return isset($this->data['error_count']) ? $this->data['error_count'] : 0;
    }

    /**
     * @return array
     */
    public function getMethods()
    {
        return isset($this->data['methods']) ? $this->data['methods'] : array();
    }

    /**
     * @return int
     */
    public function getTotalTime()
    {
        return isset($this->data['total_time']) ? $this->data['total_time'] : 0;
    }

    public function reset()
    {
        $this->data = [];
    }
}
