<?php

namespace Playbloom\Bundle\GuzzleBundle\DataCollector;

use Guzzle\Http\Message\EntityEnclosingRequestInterface;
use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\Header\HeaderCollection;
use Guzzle\Http\Message\RequestInterface as GuzzleRequestInterface;
use Guzzle\Plugin\History\HistoryPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Guzzle3DataCollector
{
    private HistoryPlugin $profiler;

    public function __construct(HistoryPlugin $profiler)
    {
        $this->profiler = $profiler;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): array
    {
        $data = [
            'calls'       => [],
            'error_count' => 0,
            'methods'     => [],
            'total_time'  => 0,
        ];

        /**
         * Aggregates global metrics about Guzzle usage
         *
         * @param array $request
         * @param array $response
         * @param array $time
         * @param bool $error
         */
        $aggregate = function ($request, $response, $time, $error) use (&$data) {

            $method = $request['method'];
            if (!isset($data['methods'][$method])) {
                $data['methods'][$method] = 0;
            }

            $data['methods'][$method]++;
            $data['total_time'] += $time['total'];
            $data['error_count'] += (int)$error;
        };

        foreach ($this->profiler as $call) {
            $request = $this->collectRequest($call);
            $response = $this->collectResponse($call);
            $time = $this->collectTime($call);
            $error = $call->getResponse()->isError();

            $aggregate($request, $response, $time, $error);

            /** @var HeaderCollection $responseHeaders */
            $wasCached = false;
            $responseHeaders = $response['headers'];
            /** @var Header $cacheHeader */
            $cacheHeader = $responseHeaders->get('x-cache');
            if (isset($cacheHeader)) {
                $wasCached = $cacheHeader->hasValue('HIT from GuzzleCache');
            }

            $data['calls'][] = [
                'request'  => $request,
                'response' => $response,
                'time'     => $time,
                'error'    => $error,
                'cached'   => $wasCached,
            ];
        }

        return $data;
    }

    /**
     * Collect & sanitize data about a Guzzle request
     *
     *
     */
    private function collectRequest(GuzzleRequestInterface $request): array
    {
        $body = null;
        if ($request instanceof EntityEnclosingRequestInterface) {
            $body = (string)$request->getBody();
        }

        return [
            'headers' => $request->getHeaders(),
            'method' => $request->getMethod(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'port' => $request->getPort(),
            'path' => $request->getPath(),
            'query' => $request->getQuery(),
            'body' => $body,
        ];
    }

    /**
     * Collect & sanitize data about a Guzzle response
     *
     *
     */
    private function collectResponse(GuzzleRequestInterface $request): array
    {
        $response = $request->getResponse();
        $body = $response->getBody(true);

        return [
            'statusCode' => $response->getStatusCode(),
            'reasonPhrase' => $response->getReasonPhrase(),
            'headers' => $response->getHeaders(),
            'body' => $body,
        ];
    }

    /**
     * Collect time for a Guzzle request
     *
     *
     */
    private function collectTime(GuzzleRequestInterface $request): array
    {
        $response = $request->getResponse();

        $timing = [
            'resolving' => [
                'start' => 0,
                'end' => $response->getInfo('namelookup_time'),
            ],
            'connecting' => [
                'start' => $response->getInfo('namelookup_time'),
                'end' => $response->getInfo('connect_time'),
            ],
            'negotiating' => [
                'start' => $response->getInfo('connect_time'),
                'end' => $response->getInfo('pretransfer_time'),
            ],
            'waiting' => [
                'start' => $response->getInfo('pretransfer_time'),
                'end' => $response->getInfo('starttransfer_time'),
            ],
            'processing' => [
                'start' => $response->getInfo('starttransfer_time'),
                'end' => $response->getInfo('total_time'),
            ],
        ];

        $total = $response->getInfo('total_time');

        foreach ($timing as &$category) {
            $category['duration'] = $category['end'] - $category['start'];
            $category['percentage'] = 0;
            $category['start_percentage'] = 0;
            if ($total != 0) {
                $category['percentage'] = $category['duration'] / $total * 100;
                $category['start_percentage'] = $category['start'] / $total * 100;
            }
        }

        $timing['total'] = $total;

        return $timing;
    }
}
