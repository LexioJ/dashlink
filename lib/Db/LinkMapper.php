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
	 * Find enabled admin links ordered by position (user_id IS NULL), excluding suggestions
	 *
	 * @return Link[]
	 */
	public function findEnabled(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('user_id'))
			->andWhere($qb->expr()->eq('is_suggestion', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->orderBy('position', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Filter links by group visibility: no groups = visible to all,
	 * otherwise user must be in at least one of the link's groups.
	 *
	 * @param Link[] $links
	 * @param string[] $userGroups
	 * @return Link[]
	 */
	public static function filterByGroups(array $links, array $userGroups): array {
		return array_values(array_filter($links, function (Link $link) use ($userGroups) {
			$linkGroups = $link->getGroups();
			if (empty($linkGroups)) {
				return true;
			}
			return !empty(array_intersect($userGroups, $linkGroups));
		}));
	}

	/**
	 * Find enabled admin links for specific user groups (user_id IS NULL)
	 *
	 * @param array $userGroups Array of group IDs the user belongs to
	 * @return Link[]
	 */
	public function findAdminLinksForUser(array $userGroups): array {
		return self::filterByGroups($this->findEnabled(), $userGroups);
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
	 * All suggestion rows (admin catalog view), enabled or not
	 *
	 * @return Link[]
	 */
	public function findSuggestions(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->isNull('user_id'))
			->andWhere($qb->expr()->eq('is_suggestion', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->orderBy('position', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Enabled suggestions visible to the given user groups
	 *
	 * @param string[] $userGroups
	 * @return Link[]
	 */
	public function findSuggestionsForUser(array $userGroups): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->isNull('user_id'))
			->andWhere($qb->expr()->eq('is_suggestion', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->orderBy('position', 'ASC');
		return self::filterByGroups($this->findEntities($qb), $userGroups);
	}

	/**
	 * Every enabled row (admin links, suggestions, and personal links) for health checking
	 *
	 * @return Link[]
	 */
	public function findAllEnabledForHealthCheck(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * Suggestion ids the user has already adopted
	 *
	 * @return int[]
	 */
	public function findAdoptedSuggestionIds(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('suggested_from')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->isNotNull('suggested_from'));
		$result = $qb->executeQuery();
		$ids = array_map('intval', array_column($result->fetchAll(), 'suggested_from'));
		$result->closeCursor();
		return $ids;
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
