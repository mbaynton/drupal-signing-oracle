# Drupal release artifact signing oracle

This process possesses the Drupal intermediate signing key and uses it to sign release artifacts.

It is sequestered from the d.o release build host, with interactions occurring over an ActiveMQ queue.

The design is kept fairly scrappy to optimize for simplicity of development and maintenance.

## Running
It's envisaged that the script `bin/signing-oracle` be run in production as a simple systemd service.

See `examples/signing-oracle.service` for an example service file.

Usage:
```bash
bin/signing-oracle -c /path/to/config.yml
```

Remember to `composer install` the dependencies first.

## Configuration
The configuration file needs to be syntactically and semantically correct, no fancy validation is being applied.

The `examples/config.yml` should be kept updated and treated as the canonical source of the configuration
directives.

## ActiveMQ queues and message format
Note that ActiveMQ queues are essentially configurationless and ephemeral. Any queue name you indicate for requests
or replies will be created as needed if it does not already exist.

### Incoming
The oracle subscribes itself as a consumer to the queue whose name is configured at `messaging.stomp.request-queue-name`.

 * Messages on this queue must have a `reply-to` header, indicating the queue name on which signed responses should be
   delivered.
 * If the message also supplies a `correlation-id` header, it will be included in the response message.
 * The message body may be a raw payload to sign, or a json document including the payload to sign along with other
   metadata.
   * When providing a raw payload to sign, set the message type to `text/plain`.
   * When providing a json document, set the message type to `application/json`, and include key `signable-payload` in
     the document. Any additional keys are assumed to be metadata that should appear in the audit record. Consider
     sending things like the sender hostname and application, what is being signed and why.

### Outgoing
If no error occurred, a `text/plain` message with be sent to the sender's desired receipt queue whose `correlation-id`
matches the request and whose body is the CSIG-format signed data.

## Logging
Logging is through [Monolog](https://github.com/Seldaek/monolog) and configured with
[monolog-cascade](https://packagist.org/packages/theorchard/monolog-cascade).

There are two logging channels:
  * `signing-oracle` receives typical application events
  * `signing-audit` receives complete csig data and associated request information of everything the oracle signs.
  
The Monolog ecosystem should make various production log and audit management scenarios achievable.

## Monitoring
 * Events of level `ERROR` [or higher](https://github.com/php-fig/log/blob/master/Psr/Log/LogLevel.php) on the 
   `signing-oracle` log channel indicate a failure preventing the oracle from doing its job, so should probably
   alert.
 * While the process is operating as expected, it pings systemd about every 10 seconds. The example service file
   configures systemd to restart the process if this stops occurring.
 * The queue consumer works with ActiveMQ in a mode whereby it must provide positive acknowledgement that
   a given message was processed successfully. If your producer marks its messages as persistent, then
   ActiveMQ will place any unacknowledged messages in a dead-letter queue for examination. The queue name
   by default is ` ActiveMQ.DLQ`. See [https://activemq.apache.org/message-redelivery-and-dlq-handling](https://activemq.apache.org/message-redelivery-and-dlq-handling)
   to customize the DLQ behavior. 