<?php


namespace Drupal\SigningOracle;


use Drupal\SigningOracle\Messaging\TempMessageProvider;
use Drupal\SigningOracle\Signing\SignifyExecutingSignerService;
use Mekras\SystemD\Watchdog;
use Pimple\Container;
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

        /*
        $this['incoming_sqs'] = function($c) {
            $sqsConfig = $c['config']['sqs'];
            return new SqsClient([
                'profile' => 'default',
                'region' => $sqsConfig['region'],
                'version' => '2012-11-05',
            ]);
        };*/

        // Must implement MessageProviderInterface.
        $this['message_provider'] = function ($c) {
            return new TempMessageProvider();
        };

        $this['queue_loop'] = function($c) {
            return new QueueLoopService(
                $c['message_provider'],
                $c['signing_service'],
                $c['sd_watchdog']
            );
        };

        // Must implement Signing\SignerServiceInterface.
        $this['signing_service'] = function($c) {
            $config = $c['config']['signing'];
            return new SignifyExecutingSignerService(
                $config['signify-binary'],
                $config['keys']['int-xpub-key-file'],
                $config['keys']['int-private-key-file']
            );
        };
    }
}