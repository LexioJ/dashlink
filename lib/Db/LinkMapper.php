<?php
declare(strict_types=1);

namespace OCA\DashLink\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Link>
 */
class LinkMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'dashlink_links', Link::class);
	}

	/**
	 * Find all admin links ordered by position (user_id IS NULL)
	 *
	 * @return Link[]
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->isNull('user_id'))
			->orderBy('position', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Find link by ID
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findById(int $id): Link {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * Find enabled admin links ordered by position (user_id IS NULL)
	 *
	 * @return Link[]
	 */
	public function findEnabled(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('user_id'))
			->orderBy('position', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Find enabled admin links for specific user groups (user_id IS NULL)
	 *
	 * @param array $userGroups Array of group IDs the user belongs to
	 * @return Link[]
	 */
	public function findAdminLinksForUser(array $userGroups): array {
		$allLinks = $this->findEnabled();

		// Filter links based on group membership
		return array_filter($allLinks, function (Link $link) use ($userGroups) {
			$linkGroups = $link->getGroups();

			// If no groups specified, link is visible to all
			if (empty($linkGroups)) {
				return true;
			}

			// Check if user is in any of the link's groups
			return !empty(array_intersect($userGroups, $linkGroups));
		});
	}

	/**
	 * Find all links for a specific user (user's private links only)
	 *
	 * @param string $userId User's UID
	 * @return Link[]
	 */
	public function findByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('position', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Find enabled links for a specific user
	 *
	 * @param string $userId User's UID
	 * @return Link[]
	 */
	public function findEnabledByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->orderBy('position', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Find a link by ID that belongs to a specific user (for ownership check)
	 *
	 * @param int $id Link ID
	 * @param string $userId User's UID
	 * @return Link
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByIdForUser(int $id, string $userId): Link {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return $this->findEntity($qb);
	}

	/**
	 * Count links for a specific user
	 *
	 * @param string $userId User's UID
	 * @return int
	 */
	public function countUserLinks(string $userId): int {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (int) ($row['count'] ?? 0);
	}

	/**
	 * Delete link by ID
	 */
	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$qb->executeStatement();
	}

	/**
	 * Update positions for multiple admin links (validates they are admin links)
	 *
	 * @param array $linkIds Array of link IDs in the desired order
	 */
	public function updatePositions(array $linkIds): void {
		$position = 0;
		foreach ($linkIds as $linkId) {
			$qb = $this->db->getQueryBuilder();

			$qb->update($this->getTableName())
				->set('position', $qb->createNamedParameter($position, IQueryBuilder::PARAM_INT))
				->set('updated_at', $qb->createNamedParameter(new \DateTime(), IQueryBuilder::PARAM_DATE))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($linkId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->isNull('user_id'));

			$qb->executeStatement();
			$position++;
		}
	}

	/**
	 * Update positions for a specific user's links
	 *
	 * @param string $userId User's UID
	 * @param array $linkIds Array of link IDs in the desired order
	 */
	public function updateUserPositions(string $userId, array $linkIds): void {
		$position = 0;
		foreach ($linkIds as $linkId) {
			$qb = $this->db->getQueryBuilder();

			$qb->update($this->getTableName())
				->set('position', $qb->createNamedParameter($position, IQueryBuilder::PARAM_INT))
				->set('updated_at', $qb->createNamedParameter(new \DateTime(), IQueryBuilder::PARAM_DATE))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($linkId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

			$qb->executeStatement();
			$position++;
		}
	}

	/**
	 * Delete all links for a specific user
	 *
	 * @param string $userId User's UID
	 */
	public function deleteByUser(string $userId): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		$qb->executeStatement();
	}
}
