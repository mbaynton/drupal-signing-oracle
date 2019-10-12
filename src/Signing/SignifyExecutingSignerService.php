<?php


namespace Drupal\SigningOracle\Signing;



use Monolog\Logger;

/**
 * Class SignifyExecutingSigner
 *
 * Creates Csig (chained) csig data with a dependency on a signify binary on the system.
 */
class SignifyExecutingSignerService implements SignerServiceInterface
{
    /**
     * @var Logger
     */
    protected $app_logger;

    protected $signify_binary;

    protected $xpub_file;

    protected $intermediate_secret_file;

    /**
     * SignifyExecutingSignerService constructor.
     * @param string $signify_binary
     * @param string $xpub_file
     * @param string $intermediate_secret_file
     *
     * @throws SigningException
     */
    public function __construct(Logger $app_logger, string $signify_binary, string $xpub_file, string $intermediate_secret_file)
    {
        $this->app_logger = $app_logger;
        $this->signify_binary = $signify_binary;
        $this->xpub_file = $xpub_file;
        $this->intermediate_secret_file = $intermediate_secret_file;

        // Run a smoketest of the environment by signing something and verifying we get a csig file.
        $exception = null;
        $smoketest_sign = '';
        try {
            $smoketest_sign = $this->signString('hello world');
        } catch (SigningException $e) {
            $exception = $e;
        }
        $matches = [];
        preg_match_all('|untrusted comment|', $smoketest_sign, $matches, PREG_PATTERN_ORDER);
        // A good csig has three "untrusted comment" lines.
        if ($exception !== null || count($matches[0]) !== 3) {
            $this->app_logger->critical('Smoke test of exec-dependent signer failed. Check signing configuration?', ['exception' => $exception]);
            if ($exception !== null) {
                throw new SigningException('Smoke test of exec-dependent signer failed. Check signing configuration?', 0, $exception);
            }
            throw new SigningException('Smoke test of exec-dependent signer failed. Check signing configuration?');
        }

        $this->app_logger->debug('BSD signify execution based signing verified operational.');
    }

    /**
     * Generates the signify signed data stream by executing Signify and streaming stdin/stdout.
     *
     * The utility of using streams is just to avoid moving php string values to/from the binary
     * via temp files. It doesn't help with memory usage and large messages under the current
     * implementation because we keep the method non-public and avoid packaging up the two
     * resources from proc_open() in a nice disposable Stream-type object; the public methods
     * then just work in terms of strings.
     *
     * We aren't planning on signing massive messages, and the Signify format was never really
     * optimized for them anyway since the signature over the message occurs before the message.
     * Otherwise, you could stream the input right back out and append the signature to the end
     * of the stream.
     *
     * @param $h_message
     * @return array
     */
    protected function signifySign($h_message)
    {
        $secret_key_escaped = escapeshellarg($this->intermediate_secret_file);
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $h_proc = proc_open("{$this->signify_binary} -S -e -s $secret_key_escaped -x - -m -", $descriptorspec, $pipes);
        stream_copy_to_stream($h_message, $pipes[0]);
        fclose($pipes[0]);
        return [$h_proc, $pipes[1], $pipes[2]];
    }

    /**
     * @param string $message
     * @return string
     * @throws SigningException
     */
    public function signString(string $message) : string
    {
        $csig = file_get_contents($this->xpub_file);

        $h_message = fopen('php://memory', 'r+');
        fwrite($h_message, $message);
        rewind($h_message);
        $resources = $this->signifySign($h_message);

        $csig .= stream_get_contents($resources[1]);
        fclose($resources[1]);
        $stderr = stream_get_contents($resources[2]);
        fclose($resources[2]);

        $exitCode = proc_close($resources[0]);
        if ($exitCode !== 0) {
            throw new SigningException('Signify did not exit 0: ' . $stderr, $exitCode);
        }

        return $csig;
    }
}