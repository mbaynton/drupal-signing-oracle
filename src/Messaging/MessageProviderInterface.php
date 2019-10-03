<?php


namespace Drupal\SigningOracle\Messaging;

/**
 * Interface MessageProviderInterface
 */
interface MessageProviderInterface
{
    /**
     * Obtians a signing request message from the messaging system.
     *
     * If there are no messages, must block until a message can be returned, or until the number of seconds
     * returned by getBlockingTime() have elapsed.
     *
     * @throws MessagingException
     */
    function getNextMessage() : SigningRequestMessage;

    /**
     * Indicates the maximum length of time that getNextMessages() will block.
     *
     * This should be nonzero to avoid busy-waiting, but not so large that the systemd watchdog does not
     * work well.
     *
     * @return int
     */
    function getBlockingTime() : int;
}