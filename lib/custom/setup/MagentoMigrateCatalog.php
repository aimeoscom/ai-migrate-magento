<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos GmbH (aimeos.com), 2020
 */


namespace Aimeos\MW\Setup\Task;


class MagentoMigrateCatalog extends \Aimeos\MW\Setup\Task\Base
{
	/**
	 * Returns the list of task names which this task depends on.
	 *
	 * @return string[] List of task names
	 */
	public function getPreDependencies() : array
	{
		return ['TablesCreateMShop'];
	}


	/**
	 * Returns the list of task names which depends on this task.
	 *
	 * @return array List of task names
	 */
	public function getPostDependencies() : array
	{
		return [];
	}


	/**
	 * Executes the task
	 */
	public function migrate()
	{
		$this->msg( 'Migrate Magento categories', 0 );

		$srcschema = $this->getSchema( 'db-magento' );

		if( $srcschema->tableExists( 'catalog_category_entity' )
			&& $srcschema->tableExists( 'catalog_category_entity_varchar' )
		) {
			$dbm = $this->additional->getDatabaseManager();

			$conn = $dbm->acquire( 'db-catalog' );
			$conn->create( 'DELETE FROM mshop_catalog' )->execute()->finish();
			$dbm->release( $conn, 'db-catalog' );

			$manager = \Aimeos\MShop::create( $this->additional, 'catalog' );
			$srcconn = $dbm->acquire( 'db-magento' );
			$map = [];

			$select = '
				SELECT ce.entity_id, ce.parent_id, cev.value
				FROM catalog_category_entity AS ce
				JOIN catalog_category_entity_varchar AS cev ON cev.entity_id = ce.entity_id
				WHERE attribute_id = 45
				ORDER BY level, position
			';
			$result = $conn->create( $select )->execute();

			while( $row = $result->fetch() )
			{
				$item = $manager->createItem()->setCode( $row['entity_id'] )->setLabel( $row['value'] );
				$map[$row['entity_id']] = $manager->insertItem( $item, $map[$row['parent_id']] ?? null )->getId();
			}

			$dbm->release( $srcconn, 'db-magento' );

			return $this->status( 'done' );
		}

		$this->status( 'OK' );
	}
}