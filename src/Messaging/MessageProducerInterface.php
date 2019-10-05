<?php


namespace Drupal\SigningOracle\Messaging;

/**
 * Interface MessageProducerInterface
 *
 * Services that produce outgoing signing response messages should implement.
 */
interface MessageProducerInterface
{
    /**
     * Sends a signing response message to the messaging system.
     *
     * @param SigningResponseMessage $message
     * @throws MessagingException
     * @return void
     */
    function sendMessage(SigningResponseMessage $message) : void;
}