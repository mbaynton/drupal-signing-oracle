# Drupal release artifact signing oracle

This process possesses the Drupal intermediate signing key and uses it to sign release artifacts.

It is sequestered from the d.o release build host, with interactions occurring over an Amazon SQS queue.

The design is kept pretty scrappy to optimize for simplicity of development and maintenance.

## Running
It's envisaged that the script `bin/signing-oracle` be run in production as a simple systemd service.

Provide to the environment `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`.

## Configuration
The configuration file needs to be syntactically and semantically correct, no fancy validation is being applied.

## Monitoring