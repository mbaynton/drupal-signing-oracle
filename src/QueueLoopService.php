<?php


namespace Drupal\SigningOracle;


use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;
use Drupal\SigningOracle\Messaging\MessageProviderInterface;
use Drupal\SigningOracle\Messaging\MessagingException;
use Drupal\SigningOracle\Messaging\NullSigningRequestMessage;
use Drupal\SigningOracle\Signing\SignerServiceInterface;
use Mekras\SystemD\Watchdog;

class QueueLoopService
{
    /**
     * @var SqsClient $messageProvider
     */
    protected $messageProvider;

    /**
     * @var SignerServiceInterface $signer
     */
    protected $signer;

    /**
     * @var Watchdog $systemdWatchdog
     */
    protected $systemdWatchdog;

    protected $config;

    public function __construct(MessageProviderInterface $messageProvider, SignerServiceInterface $signer, Watchdog $sd_watchdog)
    {
        $this->messageProvider = $messageProvider;
        $this->signer = $signer;
        $this->systemdWatchdog = $sd_watchdog;
    }

    public function queueLoop()
    {
        while(true)
        {
            $this->systemdWatchdog->ping();

            try {
                $queueMessage = $this->messageProvider->getNextMessage();

                if ($queueMessage instanceof NullSigningRequestMessage) {
                    continue;
                }

                // TODO: handle signing exceptoins
                // TODO: add payload extractor factory / mechanism
                $csig = $this->signer->signString($queueMessage->signable_payload);
                echo $csig;
            } catch (MessagingException $e) {
                var_dump($e);
                // TODO: determine how long to wait until retrying or exiting.
            }
        }
    }
}