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
use Monolog\Logger;

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

    /**
     * @var Logger $app_logger
     */
    protected $app_logger;

    /**
     * @var Logger $audit_logger
     */
    protected $audit_logger;

    public function __construct(MessageConsumerInterface $messageConsumer, MessageProducerInterface $messageProducer, SignerServiceInterface $signer, Watchdog $sd_watchdog, Logger $app_logger, Logger $audit_logger)
    {
        $this->messageConsumer = $messageConsumer;
        $this->messageProducer = $messageProducer;
        $this->signer = $signer;
        $this->systemdWatchdog = $sd_watchdog;
        $this->app_logger = $app_logger;
        $this->audit_logger = $audit_logger;
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
                $this->app_logger->critical('Message consumer exception', ['exception' => $e]);
                exit(1);
            }

            if ($queueMessage instanceof NullSigningRequestMessage) {
                $this->app_logger->debug('Message consumer returned control with no new messages.');
                continue;
            }

            if (empty($queueMessage->reply_to)) {
                $this->app_logger->notice('Unprocessable message received: no reply-to.', $queueMessage->summarize());
                $this->messageConsumer->nack($queueMessage->ack_handle);
                continue;
            }

            $this->app_logger->debug('Accepted request message for signing.', $queueMessage->summarize());

            // TODO: add payload extractor factory / mechanism to support payload types other than 'inline'
            try {
                $csig = $this->signer->signString($queueMessage->signable_payload);
            } catch (SigningException $exception) {
                $this->messageConsumer->nack($queueMessage->ack_handle);
                continue;
            }

            $response = new SigningResponseMessage('inline', $csig, $queueMessage->reply_to, $queueMessage->correlation_id);

            // PSR-3 says logging methods ought not to throw, but Monolog 1.x does, necessitating all this.
            $e = null;
            $is_audited = false;
            try {
                $is_audited = $this->audit_logger->notice($csig, $queueMessage->summarize());
            } catch (\Exception $e) {}
            finally {
                if ($e !== null || ! $is_audited) {
                    $this->app_logger->error(
                        'Failed to record audit of a signing. For security, the signed message will not be released.',
                        $queueMessage->summarize() + ['exception' => $e]
                    );
                    $this->messageConsumer->nack($queueMessage->ack_handle);

                    if ($e !== null) {
                        // We didn't really handle this exception and don't have control of the state of things.
                        throw $e;
                    }
                }
            }
            if (! $is_audited) {
                continue;
            }

            try {
                $this->messageProducer->sendMessage($response);
            } catch (MessagingException $e) {
                $this->messageConsumer->nack($queueMessage->ack_handle);
                continue;
            }
            $this->messageConsumer->ack($queueMessage->ack_handle);
            $this->app_logger->info('Received and signed message with correlation id "{correlation-id}".', $queueMessage->summarize());
        }
    }
}
