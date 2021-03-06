<?php

namespace Playbloom\Bundle\GuzzleBundle\Subscriber;

use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Transaction;

class TransactionRecorder implements SubscriberInterface
{
    /** @var Transaction[] */
    private $transactions = array();

    /** @var int The maximum number of requests to maintain in the history */
    private $limit;

    public function __construct($limit = 10)
    {
        $this->limit = $limit;
    }

    public function getEvents()
    {
        return [
            'complete' => ['onComplete', RequestEvents::LATE],
            'error'    => ['onError', RequestEvents::LATE],
        ];
    }

    public function onComplete(CompleteEvent $event)
    {
        $this->add($event->getTransaction());
    }

    public function onError(ErrorEvent $event)
    {
        $lightTx = clone $event->getTransaction();
        unset($lightTx->exception);
        $this->add($lightTx);
    }

    private function add(Transaction $transaction)
    {
        $transaction->stackTrace = debug_backtrace();
        $this->transactions[] = $transaction;
        if (count($this->transactions) > $this->limit) {
            array_shift($this->transactions);
        }
    }

    /**
     * @return Transaction[]
     */
    public function getTransactions()
    {
        return array_values($this->transactions); // be zero based in case we array_shift'ed
    }
}
