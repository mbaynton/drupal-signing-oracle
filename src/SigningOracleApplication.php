<?php


namespace Drupal\SigningOracle;



use Cascade\Cascade;
use Monolog\Logger;
use function Mekras\SystemD\sd_notify;

class SigningOracleApplication
{
    protected $container;

    public function __construct(\ArrayAccess $options)
    {
        $this->container = new SigningOracleContainer([
            'configFile' => $options['config'],
        ]);
        Cascade::fileConfig($options['config']);
    }

    public function run()
    {
        /** @var Logger $log */
        $log = $this->container['app_logger'];
        $log->notice('signing-oracle startup', ['script' => __FILE__]);
        sd_notify(0, 'READY=1');
        $this->container['queue_loop']->queueLoop();
    }
}