<?php


namespace Drupal\SigningOracle;


use Cascade\Cascade;
use Drupal\SigningOracle\Messaging\ActiveMQMessageConsumer;
use Drupal\SigningOracle\Messaging\ActiveMQMessageProducer;
use Drupal\SigningOracle\Signing\SignifyExecutingSignerService;
use Mekras\SystemD\Watchdog;
use Pimple\Container;
use Stomp\Client as StompClient;
use Stomp\Network\Connection as StompConnection;
use Stomp\StatefulStomp;
use Symfony\Component\Yaml\Yaml;

class SigningOracleContainer extends Container
{
    public function __construct(array $values = array())
    {
        parent::__construct($values);

        $this->loadConfig($values['configFile']);

        $this->registerServices();
    }

    protected function loadConfig(string $filename)
    {
        $this['config'] = Yaml::parseFile($filename);
    }

    protected function registerServices()
    {
        $this['sd_watchdog'] = function ($c) {
            return new Watchdog();
        };

        $this['stomp_client'] = function($c) {
            $config = $c['config']['messaging']['stomp'];
            $connection = new StompConnection($config['brokerUri']);
            $connection->setReadTimeout(10);
            $client = new StompClient($connection);
            if(! empty($config['username'] . $config['password'])) {
                $client->setLogin($config['username'], $config['password']);
            }
            return new StatefulStomp($client);
        };

        // Must implement MessageProviderInterface.
        $this['message_consumer'] = function ($c) {
            /** @var StatefulStomp $stomp */
            $stomp = $c['stomp_client'];
            $config = $c['config']['messaging']['stomp'];
            $stomp->subscribe("/queue/{$config['request-queue-name']}", null, 'client-individual');

            return new ActiveMQMessageConsumer($stomp, $c['app_logger']);
        };

        $this['message_producer'] = function ($c) {
            return new ActiveMQMessageProducer($c['stomp_client'], $c['app_logger']);
        };

        $this['queue_loop'] = function($c) {
            return new QueueLoopService(
                $c['message_consumer'],
                $c['message_producer'],
                $c['signing_service'],
                $c['sd_watchdog'],
                $c['app_logger'],
                $c['audit_logger']
            );
        };

        // Must implement Signing\SignerServiceInterface.
        $this['signing_service'] = function($c) {
            $config = $c['config']['signing'];
            return new SignifyExecutingSignerService(
                $c['app_logger'],
                $config['signify-binary'],
                $config['keys']['int-xpub-key-file'],
                $config['keys']['int-private-key-file']
            );
        };

        $this['app_logger'] = function ($c) {
            return Cascade::getLogger('signing-oracle');
        };
        $this['audit_logger'] = function ($c) {
            return Cascade::getLogger('signing-audit');
        };
    }
}