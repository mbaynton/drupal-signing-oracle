<?php


namespace Drupal\SigningOracle\Messaging;

/**
 * Interface MessageConsumerInterface
 *
 * Services that consume incoming signing request messages should implement.
 */
interface MessageConsumerInterface
{
    /**
     * Obtians a signing request message from the messaging system.
     *
     * If there are no messages, must block until a message can be returned, or until a timeout expires.
     *
     * May return a NullSigningRequestMessage at any time when no real message is available.
     *
     * @throws MessagingException
     */
    function getNextMessage() : SigningRequestMessage;

    /**
     * Informs the message broker that a given message has been processed successfully.
     *
     * @param mixed $ack_handle
     */
    function ack($ack_handle) : void;

    /**
     * Informs the message broker that a given message was not processed successfully.
     *
     * @param mixed $ack_handle
     */
    function nack($ack_handle) : void;
}