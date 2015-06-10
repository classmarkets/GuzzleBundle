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
        return array(
            'headers' => $request->getHeaders(),
            'method'  => $request->getMethod(),
            'scheme'  => $request->getScheme(),
            'host'    => $request->getHost(),
            'port'    => $request->getPort(),
            'path'    => $request->getPath(),
            'query'   => $request->getQuery(),
            'body'    => (string) $request->getBody(),
        );
    }

    private function collectResponse(ResponseInterface $response)
    {
        $body = $response->getBody();

        return array(
            'statusCode'   => $response->getStatusCode(),
            'reasonPhrase' => $response->getReasonPhrase(),
            'headers'      => $response->getHeaders(),
            'is_error'     => $response->getStatusCode() >= 400,
            'body'         => $body,
        );
    }

    private function collectTime(array $transferInfo)
    {
        return [
            'total'      => isset($transferInfo['total_time']) ? $transferInfo['total_time'] : 0,
            'connection' => isset($transferInfo['connect_time']) ? $transferInfo['connect_time'] : 0,
        ];
    }
}
