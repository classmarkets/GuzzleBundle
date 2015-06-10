<?php

namespace Playbloom\Bundle\GuzzleBundle\DataCollector;

use Guzzle\Plugin\History\HistoryPlugin;

use Guzzle\Http\Message\RequestInterface as GuzzleRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Guzzle\Http\Message\EntityEnclosingRequestInterface;

class Guzzle3DataCollector
{
    private $profiler;

    public function __construct(HistoryPlugin $profiler)
    {
        $this->profiler = $profiler;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $data = array(
            'calls'       => array(),
            'error_count' => 0,
            'methods'     => array(),
            'total_time'  => 0,
        );

        /**
         * Aggregates global metrics about Guzzle usage
         *
         * @param array $request
         * @param array $response
         * @param array $time
         * @param bool  $error
         */
        $aggregate = function ($request, $response, $time, $error) use (&$data) {

            $method = $request['method'];
            if (!isset($data['methods'][$method])) {
                $data['methods'][$method] = 0;
            }

            $data['methods'][$method]++;
            $data['total_time'] += $time['total'];
            $data['error_count'] += (int) $error;
        };

        foreach ($this->profiler as $call) {
            $request = $this->collectRequest($call);
            $response = $this->collectResponse($call);
            $time = $this->collectTime($call);
            $error = $call->getResponse()->isError();

            $aggregate($request, $response, $time, $error);

            $data['calls'][] = array(
                'request' => $request,
                'response' => $response,
                'time' => $time,
                'error' => $error
            );
        }

        return $data;
    }

    /**
     * Collect & sanitize data about a Guzzle request
     *
     * @param \Guzzle\Http\Message\RequestInterface $request
     *
     * @return array
     */
    private function collectRequest(GuzzleRequestInterface $request)
    {
        $body = null;
        if ($request instanceof EntityEnclosingRequestInterface) {
            $body = (string) $request->getBody();
        }

        return array(
            'headers' => $request->getHeaders(),
            'method'  => $request->getMethod(),
            'scheme'  => $request->getScheme(),
            'host'    => $request->getHost(),
            'port'    => $request->getPort(),
            'path'    => $request->getPath(),
            'query'   => $request->getQuery(),
            'body'    => $body
        );
    }

    /**
     * Collect & sanitize data about a Guzzle response
     *
     * @param \Guzzle\Http\Message\RequestInterface $request
     *
     * @return array
     */
    private function collectResponse(GuzzleRequestInterface $request)
    {
        $response = $request->getResponse();
        $body = $response->getBody(true);

        return array(
            'statusCode'   => $response->getStatusCode(),
            'reasonPhrase' => $response->getReasonPhrase(),
            'headers'      => $response->getHeaders(),
            'body'         => $body
        );
    }

    /**
     * Collect time for a Guzzle request
     *
     * @param \Guzzle\Http\Message\RequestInterface $request
     *
     * @return array
     */
    private function collectTime(GuzzleRequestInterface $request)
    {
        $response = $request->getResponse();

        return array(
            'total'      => $response->getInfo('total_time'),
            'connection' => $response->getInfo('connect_time')
        );
    }
}
