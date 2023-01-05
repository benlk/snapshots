<?php
/**
 * Logging trait.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Log;

/**
 * Logging trait.
 *
 * @package TenUp\WPSnapshots\Log
 */
trait Logging {

	/**
	 * Logger instance.
	 *
	 * @var ?LoggerInterface
	 */
	private $logger;

	/**
	 * Sets the logger instance.
	 *
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function set_logger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Gets the logger instance.
	 *
	 * @return LoggerInterface
	 */
	public function get_logger(): LoggerInterface {
		return $this->logger;
	}

	/**
	 * Logs a message.
	 *
	 * @param string $message Message to log.
	 * @param string $type Type of message.
	 */
	public function log( string $message, $type = 'info' ) {
		if ( $this->logger ) {
			$this->logger->log( $message, $type );
		}
	}
}
