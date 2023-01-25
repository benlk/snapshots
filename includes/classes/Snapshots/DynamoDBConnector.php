<?php
/**
 * Amazon Dynamo wrapper functionality
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

/**
 * Class for handling Amazon dynamodb calls
 */
class DynamoDBConnector implements DBConnectorInterface {

	/**
	 * Clients keyed by region.
	 *
	 * @var DynamoDbClient[]
	 */
	private $clients = [];

	/**
	 * Searches the database.
	 *
	 * @param  string|array $query Search query string
	 * @param  string       $repository Repository name
	 * @param string       $region AWS region
	 * @return array
	 */
	public function search( $query, string $repository, string $region ) : array {
		$marshaler = new Marshaler();

		$args = [
			'TableName' => 'wpsnapshots-' . $repository,
		];

		if ( ! is_array( $query ) ) {
			$query = [ $query ];
		}

		if ( ! in_array( '*', $query, true ) ) {
			$attribute_value_list = array_map(
				function( $text ) {
					return [ 'S' => strtolower( $text ) ];
				},
				$query
			);

			$is_multiple_queries = count( $attribute_value_list ) > 1;

			$args['ConditionalOperator'] = 'OR';
			$args['ScanFilter']          = [
				'project' => [
					'AttributeValueList' => $attribute_value_list,
					'ComparisonOperator' => $is_multiple_queries ? 'IN' : 'CONTAINS',
				],
				'id'      => [
					'AttributeValueList' => $attribute_value_list,
					'ComparisonOperator' => $is_multiple_queries ? 'IN' : 'EQ',
				],
			];
		}

		$search_scan = $this->get_client( $region )->getIterator( 'Scan', $args );

		$instances = [];

		foreach ( $search_scan as $item ) {
			$instances[] = $marshaler->unmarshalItem( (array) $item );
		}

		return $instances;
	}

	/**
	 * Get a snapshot given an id
	 *
	 * @param  string $id Snapshot ID
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 * @return mixed
	 */
	public function get_snapshot( string $id, string $repository, string $region ) {
		$result = $this->get_client( $region )->getItem(
			[
				'ConsistentRead' => true,
				'TableName'      => 'wpsnapshots-' . $repository,
				'Key'            => [
					'id' => [
						'S' => $id,
					],
				],
			]
		);

		if ( empty( $result['Item'] ) ) {
			return false;
		}

		if ( ! empty( $result['Item']['error'] ) ) {
			return false;
		}

		$marshaler = new Marshaler();

		return $marshaler->unmarshalItem( $result['Item'] );
	}

	/**
	 * Create default DB tables. Only need to do this once ever for repo setup.
	 *
	 * @param string $repository Repository name.
	 * @param string $region AWS region.
	 */
	public function create_tables( string $repository, string $region ) {
		$table_name = 'wpsnapshots-' . $repository;
		$client     = $this->get_client( $region );

		$client->createTable(
			[
				'TableName'             => $table_name,
				'AttributeDefinitions'  => [
					[
						'AttributeName' => 'id',
						'AttributeType' => 'S',
					],
				],
				'KeySchema'             => [
					[
						'AttributeName' => 'id',
						'KeyType'       => 'HASH',
					],
				],
				'ProvisionedThroughput' => [
					'ReadCapacityUnits'  => 10,
					'WriteCapacityUnits' => 20,
				],
			]
		);

		$client->waitUntil(
			'TableExists',
			[
				'TableName' => $table_name,
			]
		);
	}

	/**
	 * Provides the client.
	 *
	 * @param string $region AWS region.
	 *
	 * @return DynamoDbClient
	 */
	private function get_client( string $region ) : DynamoDbClient {
		if ( ! isset( $this->clients[ $region ] ) ) {
			$this->clients[ $region ] = new DynamoDbClient(
				[
					'version' => 'latest',
					'region'  => $region,
				]
			);
		}

		return $this->clients[ $region ];
	}
}
