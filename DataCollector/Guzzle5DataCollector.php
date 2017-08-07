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

    /** @var string Name of the project root directory. Will be stripped from stack traces */
    private $projectRoot;

    public function __construct(TransactionRecorder $guzzle5Profiler, $kernelRoot)
    {
        $this->transactionRecorder = $guzzle5Profiler;
        $this->projectRoot = dirname($kernelRoot) . DIRECTORY_SEPARATOR;
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
            $requestData = $this->collectRequest($transaction->request);
            $responseData = $this->collectResponse($transaction->response);
            $time = $this->collectTime($transaction->transferInfo);
            $trace = $this->collectStackTrace($transaction);

            $method = $requestData['method'];
            if (!isset($data['methods'][$method])) {
                $data['methods'][$method] = 0;
            }

            $data['methods'][$method]++;
            $data['total_time'] += $time['total'];
            $data['error_count'] += (int) $responseData['is_error'];

            $wasCached = $transaction->request->getConfig()->get('cache_hit');

            $data['calls'][] = [
                'request' => $requestData,
                'response' => $responseData,
                'time' => $time,
                'error' => $responseData['is_error'],
                'cached' => $wasCached,
                'trace' => $trace,
            ];
        }

        return $data;
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

    private function collectResponse(ResponseInterface $response = null)
    {
        if ($response === null) {
            return [
                'statusCode' => '-',
                'reasonPhrase' => 'no response',
                'headers' => [],
                'is_error' => true,
                'body' => '',
            ];
        }

        if ($response instanceof \GuzzleHttp\Message\FutureResponse) {
            try {
                $response->wait();
            } catch (\Exception $e) {
                // wait() may throw an exception again that happend during
                // response handling (such as in a custom response parser).
                // There doesn't seem to be a way to get any information about
                // the response in this case.
                return [
                    'statusCode' => '-',
                    'reasonPhrase' => 'Exception',
                    'headers' => [
                        'Exception' => $e->getMessage(),
                    ],
                    'is_error' => true,
                    'body' => '',
                ];
            }
        }

        $responseBody = '';
        if ($body = $response->getBody()) {
            $responseBody = (string) $body;
        }

        return [
            'statusCode'   => $response->getStatusCode(),
            'reasonPhrase' => $response->getReasonPhrase(),
            'headers'      => $response->getHeaders(),
            'is_error'     => $response->getStatusCode() >= 400,
            'body'         => $responseBody,
        ];
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

    private function collectStackTrace(Transaction $transaction)
    {
        if (!isset($transaction->stackTrace)) {
            return [];
        }

        $rootLen = strlen($this->projectRoot);
        $stack = [];
        foreach ($transaction->stackTrace as $frame) {
            if (!empty($frame['class'])) {
                if (strpos($frame['class'], 'Playbloom\Bundle\GuzzleBundle') === 0) {
                    continue;
                }
            }
            if (empty($frame['file'])) {
                $frame['file'] = '';
            } else if (strpos($frame['file'], $this->projectRoot) === 0) {
                $frame['file'] = substr($frame['file'], $rootLen);
            }

            unset($frame['object']); // can't always be serialized
            unset($frame['args']);   // can't always be serialized
            unset($frame['type']);   // "->", "::', etc; not interested

            $stack[] = $frame;
        }
        return $stack;
    }
}
