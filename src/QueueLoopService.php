<?php


namespace Drupal\SigningOracle;


use Drupal\SigningOracle\Messaging\MessageConsumerInterface;
use Drupal\SigningOracle\Messaging\MessageProducerInterface;
use Drupal\SigningOracle\Messaging\MessagingException;
use Drupal\SigningOracle\Messaging\NullSigningRequestMessage;
use Drupal\SigningOracle\Messaging\SigningRequestMessage;
use Drupal\SigningOracle\Messaging\SigningResponseMessage;
use Drupal\SigningOracle\Signing\SignerServiceInterface;
use Drupal\SigningOracle\Signing\SigningException;
use Mekras\SystemD\Watchdog;

class QueueLoopService
{
    /**
     * @var MessageConsumerInterface $messageConsumer
     */
    protected $messageConsumer;

    /**
     * @var MessageProducerInterface
     */
    protected $messageProducer;

    /**
     * @var SignerServiceInterface $signer
     */
    protected $signer;

    /**
     * @var Watchdog $systemdWatchdog
     */
    protected $systemdWatchdog;

    public function __construct(MessageConsumerInterface $messageConsumer, MessageProducerInterface $messageProducer, SignerServiceInterface $signer, Watchdog $sd_watchdog)
    {
        $this->messageConsumer = $messageConsumer;
        $this->messageProducer = $messageProducer;
        $this->signer = $signer;
        $this->systemdWatchdog = $sd_watchdog;
    }

    public function queueLoop()
    {
        while(true)
        {
            $this->systemdWatchdog->ping();

            try {
                $queueMessage = $this->messageConsumer->getNextMessage();
            } catch (MessagingException $e) {
                // TODO: determine causes of failure, throw this, and appropriate responses
                exit(1);
            }

            if ($queueMessage instanceof NullSigningRequestMessage) {
                continue;
            }

            if (empty($queueMessage->reply_to)) {
                $this->messageConsumer->nack($queueMessage->ack_handle);
                continue;
            }

            // TODO: add payload extractor factory / mechanism to support payload types other than 'inline'
            try {
                $csig = $this->signer->signString($queueMessage->signable_payload);
            } catch (SigningException $exception) {
                $this->messageConsumer->nack($queueMessage->ack_handle);
                continue;
            }

            $response = new SigningResponseMessage('inline', $csig, $queueMessage->reply_to, $queueMessage->correlation_id);

            try {
                $this->messageProducer->sendMessage($response);
            } catch (MessagingException $e) {
                $this->messageConsumer->nack($queueMessage->ack_handle);
                continue;
            }
            $this->messageConsumer->ack($queueMessage->ack_handle);
        }
    }
}
