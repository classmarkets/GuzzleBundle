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
    private array $transactions = [];

    /** @var int The maximum number of requests to maintain in the history */
    private int $limit;

    public function __construct(int $limit = 10)
    {
        $this->limit = $limit;
    }

    public function getEvents(): array
    {
        return [
            'complete' => ['onComplete', RequestEvents::LATE],
            'error'    => ['onError', RequestEvents::LATE],
        ];
    }

    public function onComplete(CompleteEvent $event): void
    {
        $this->add($event->getTransaction());
    }

    public function onError(ErrorEvent $event): void
    {
        $lightTx = clone $event->getTransaction();
        unset($lightTx->exception);
        $this->add($lightTx);
    }

    private function add(Transaction $transaction): void
    {
        $transaction->transferInfo['stackTrace'] = debug_backtrace();
        $this->transactions[] = $transaction;
        if (count($this->transactions) > $this->limit) {
            array_shift($this->transactions);
        }
    }

    /**
     * @return Transaction[]
     */
    public function getTransactions(): array
    {
        return array_values($this->transactions); // be zero based in case we array_shift'ed
    }
}
