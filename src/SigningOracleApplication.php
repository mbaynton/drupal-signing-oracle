<?php


namespace Drupal\SigningOracle;



use function Mekras\SystemD\sd_notify;

class SigningOracleApplication
{
    protected $container;

    public function __construct(\ArrayAccess $options)
    {
        $this->container = new SigningOracleContainer([
            'configFile' => $options['config'],
        ]);
    }

    public function run()
    {
        sd_notify(0, 'READY=1');
        $this->container['queue_loop']->queueLoop();
    }
}