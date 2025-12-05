<?php

namespace Playbloom\Bundle\GuzzleBundle\DependencyInjection;

use Playbloom\Bundle\GuzzleBundle\DataCollector\CompositeGuzzleDataCollector;
use Playbloom\Bundle\GuzzleBundle\DataCollector\Guzzle3DataCollector;
use Playbloom\Bundle\GuzzleBundle\DataCollector\Guzzle5DataCollector;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class PlaybloomGuzzleExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $hasGuzzle3 = class_exists('\Guzzle\Http\Client');
        $hasGuzzle5 = class_exists('\GuzzleHttp\Client') && class_exists('\GuzzleHttp\Collection');

        if ($hasGuzzle3) {
            $logPlugin = $container->register('playbloom_guzzle.client.plugin.logger');
            $logPlugin
                ->setClass('Guzzle\Plugin\Log\LogPlugin')
                ->setPublic(true)
                ->addArgument(new Reference('playbloom_guzzle.client.plugin.logger_adapter'))
                ->addArgument('Requested "{host}" {method} "{resource}"')
                ->addTag('playbloom_guzzle.client.plugin')
            ;

            $monologLogAdapter = $container->register('playbloom_guzzle.client.plugin.logger_adapter');
            $monologLogAdapter
                ->setClass('Guzzle\Log\MonologLogAdapter')
                ->setPublic(false)
                ->addArgument(new Reference('logger'))
                ->addTag('monolog.logger', ['channel' => 'guzzle'])
            ;

            $historyPlugin = $container->register('playbloom_guzzle.client.plugin.profiler');
            $historyPlugin
                ->setPublic(true)
                ->addMethodCall('setLimit', [100])
            ;
        }

        if ($hasGuzzle5) {
            $logSubscriber = $container->register('playbloom_guzzlehttp.client.plugin.logger');
            $logSubscriber
                ->setClass('GuzzleHttp\Subscriber\Log\LogSubscriber')
                ->setPublic(true)
                ->addArgument(new Reference('logger'))
                ->addArgument('Requested "{host}" {method} "{resource}"')
                ->addTag('playbloom_guzzlehttp.client.plugin')
            ;

            $transactionRecorder = $container->register('playbloom_guzzlehttp.client.plugin.profiler');
            $transactionRecorder
                ->setClass('Playbloom\Bundle\GuzzleBundle\Subscriber\TransactionRecorder')
                ->setPublic(true)
                ->addArgument(100)
                ->addTag('playbloom_guzzlehttp.client.plugin')
            ;
        }

        if ($config['web_profiler']) {
            $dataCollector = $container->register('data_collector.guzzle');
            $dataCollector
                ->setClass(CompositeGuzzleDataCollector::class)
                ->addTag('data_collector', [
                    'template' => '@PlaybloomGuzzle/Collector/guzzle',
                    'id'       => 'guzzle',
                ])
            ;

            if ($hasGuzzle3) {
                $guzzle3 = $container->register('data_collector.guzzle_3');
                $guzzle3->setClass(Guzzle3DataCollector::class);
                $guzzle3->addArgument(new Reference('playbloom_guzzle.client.plugin.profiler'));

                $dataCollector->addArgument(new Reference('data_collector.guzzle_3'));
            }

            if ($hasGuzzle5) {
                $guzzle5 = $container->register('data_collector.guzzle_5');
                $guzzle5->setClass(Guzzle5DataCollector::class);
                $guzzle5
                    ->addArgument(new Reference('playbloom_guzzlehttp.client.plugin.profiler'))
                    ->addArgument('%kernel.root_dir%')
                ;

                $dataCollector->addArgument(new Reference('data_collector.guzzle_5'));
            }
        }
    }
}
