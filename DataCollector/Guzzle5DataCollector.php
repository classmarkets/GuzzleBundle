<?php

namespace Playbloom\Bundle\GuzzleBundle\DataCollector;

use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Transaction;
use Playbloom\Bundle\GuzzleBundle\Subscriber\TransactionRecorder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Guzzle5DataCollector
{
    /** @var TransactionRecorder */
    private $transactionRecorder;

    public function __construct(TransactionRecorder $guzzle5Profiler)
    {
        $this->transactionRecorder = $guzzle5Profiler;
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $data = [
            'calls'       => [],
            'methods'     => [],
            'error_count' => 0,
            'total_time'  => 0,
        ];

        foreach ($this->transactionRecorder->getTransactions() as $transaction) {
            list($requestData, $responseData, $time) = $this->collectGuzzle5Transaction($transaction);

            $method = $requestData['method'];
            if (!isset($data['methods'][$method])) {
                $data['methods'][$method] = 0;
            }

            $data['methods'][$method]++;
            $data['total_time'] += $time['total'];
            $data['error_count'] += (int) $responseData['is_error'];

            $data['calls'][] = [
                'request' => $requestData,
                'response' => $responseData,
                'time' => $time,
                'error' => $responseData['is_error']
            ];
        }

        return $data;
    }

    private function collectGuzzle5Transaction(Transaction $transaction)
    {
        return [
            $this->collectRequest($transaction->request),
            $this->collectResponse($transaction->response),
            $this->collectTime($transaction->transferInfo),
        ];
    }

    private function collectRequest(RequestInterface $request)
    {
        $requestBody = '';
        if ($body = $request->getBody()) {
            $requestBody = (string) $body;
        }

        return array(
            'headers' => $request->getHeaders(),
            'method'  => $request->getMethod(),
            'scheme'  => $request->getScheme(),
            'host'    => $request->getHost(),
            'port'    => $request->getPort(),
            'path'    => $request->getPath(),
            'query'   => new Guzzle5Query($request->getQuery()),
            'body'    => $requestBody,
        );
    }

    private function collectResponse(ResponseInterface $response)
    {
        $responseBody = '';
        if ($body = $response->getBody()) {
            $responseBody = (string) $body;
        }

        return array(
            'statusCode'   => $response->getStatusCode(),
            'reasonPhrase' => $response->getReasonPhrase(),
            'headers'      => $response->getHeaders(),
            'is_error'     => $response->getStatusCode() >= 400,
            'body'         => $responseBody,
        );
    }

    private function collectTime(array $transferInfo)
    {
        $timing = array();
        if (isset($transferInfo['namelookup_time'])) {
            $timing['resolving'] = array(
                'start' => 0,
                'end' => $transferInfo['namelookup_time'],
            );
        }

        if (isset($transferInfo['namelookup_time']) && isset($transferInfo['connect_time'])) {
            $timing['connecting'] = array(
                'start' => $transferInfo['namelookup_time'],
                'end' => $transferInfo['connect_time'],
            );
        }

        if (isset($transferInfo['connect_time']) && isset($transferInfo['pretransfer_time'])) {
            $timing['negotiating'] = array(
                'start' => $transferInfo['connect_time'],
                'end' => $transferInfo['pretransfer_time'],
            );
        }

        if (isset($transferInfo['pretransfer_time']) && isset($transferInfo['starttransfer_time'])) {
            $timing['waiting'] =  array(
                'start' => $transferInfo['pretransfer_time'],
                'end' => $transferInfo['starttransfer_time'],
            );
        }

        if (isset($transferInfo['starttransfer_time']) && isset($transferInfo['total_time'])) {
            $timing['processing'] =  array(
                'start' => $transferInfo['starttransfer_time'],
                'end' => $transferInfo['total_time'],
            );
        }

        $total = 0;
        if (isset($transferInfo['total_time'])) {
            $total = $transferInfo['total_time'];
        }

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
